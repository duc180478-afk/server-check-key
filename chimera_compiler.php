```php
<?php
/**
 * chimera_compiler.php — Trình biên dịch đa hình Chimera-VM (.chmr) chạy trực tiếp trên PHP Server
 * Khớp 100% từng bit với Chimera_Builder.py và C++ ChimeraVM::decryptBlobAsset (libchimera_core.so).
 *
 * Khi Admin thêm/sửa/xoá mod trên trang quản trị (admin/mods.php) hoặc khi App gọi api_mods.php:
 *   1. Server lấy danh sách mod từ DB / data.json
 *   2. Nén zlib mức 9 và biên dịch thành luồng lệnh 32-bit đan xen:
 *      - 50% IronBrew2 Register VM (IB2_LOADK, IB2_XOR, IB2_ADD, IB2_ROR)
 *      - 50% Custom Stack + Feistel 2 vòng + Rolling State biến thiên theo PC (CUS_PUSH, CUS_FEISTEL_MIX, CUS_EMIT, CUS_HALT)
 *   3. Sinh bảng 8 V-Opcode đa hình (opcode_map) + 16-byte Server Key Share
 *   4. Đóng gói thành khung nhị phân 24B + CHMR:
 *      [8-byte Opcode Map] + [16-byte Server Key Share] + [Payload .chmr]
 *   5. Chỉ có libchimera_core.so (sở hữu nửa khóa XOM Client_Key_Share = 0x8F4A2C91E73B6D05 trong vùng nhớ PROT_EXEC)
 *      mới giải mã được gói nhị phân này trên RAM!
 */

class ChimeraServerCompiler {
    const CHIMERA_MAGIC        = "CHMR";
    const CHIMERA_VERSION      = 0x01;
    const DATA_TYPE_STRING_URL = 0x01;
    const FLAG_ZLIB_COMPRESSED = 0x0001;
    const FLAG_INTERLEAVED_VM  = 0x0002;

    // 8 Canonical V-Opcodes (0..7)
    const CANON_IB2_LOADK       = 0;
    const CANON_IB2_XOR         = 1;
    const CANON_IB2_ADD         = 2;
    const CANON_IB2_ROR         = 3;
    const CANON_CUS_PUSH        = 4;
    const CANON_CUS_FEISTEL_MIX = 5;
    const CANON_CUS_EMIT        = 6;
    const CANON_CUS_HALT        = 7;

    // Nửa khóa Client nằm trong vùng nhớ XOM (PROT_EXEC) của libchimera_core.so: 0x8F4A2C91E73B6D05
    // Tách thành 2 nửa 32-bit để tính toán chính xác tuyệt đối trên cả PHP 64-bit có dấu:
    const CLIENT_KEY_LOW32  = 0xE73B6D05;
    const CLIENT_KEY_HIGH32 = 0x8F4A2C91;

    private static function u32($val): int {
        return $val & 0xFFFFFFFF;
    }

    private static function ror32(int $val, int $shift): int {
        $shift &= 31;
        $val &= 0xFFFFFFFF;
        if ($shift === 0) return $val;
        return ((($val >> $shift) & ((1 << (32 - $shift)) - 1)) | (($val << (32 - $shift)) & 0xFFFFFFFF)) & 0xFFFFFFFF;
    }

    private static function rol32(int $val, int $shift): int {
        $shift &= 31;
        $val &= 0xFFFFFFFF;
        if ($shift === 0) return $val;
        return ((($val << $shift) & 0xFFFFFFFF) | (($val >> (32 - $shift)) & ((1 << $shift) - 1))) & 0xFFFFFFFF;
    }

    private static function feistelRoundF(int $halfVal, int $roundKey): int {
        $x = $halfVal & 0xFFFF;
        $k = $roundKey & 0xFFFF;
        $mixed = (($x * 0x45D9) + $k) & 0xFFFF;
        $rotated = (($x >> 5) | (($x << 11) & 0xFFFF)) & 0xFFFF;
        return ($mixed ^ $rotated) & 0xFFFF;
    }

    private static function feistelEncryptWord32(int $targetPlainWord, int $rollingState, int $imm16): int {
        $left  = ($targetPlainWord >> 16) & 0xFFFF;
        $right = $targetPlainWord & 0xFFFF;

        $k0 = (($rollingState >> 16) ^ $imm16) & 0xFFFF;
        $k1 = (($rollingState & 0xFFFF) ^ (($imm16 << 3) & 0xFFFF)) & 0xFFFF;

        $right ^= self::feistelRoundF($left, $k0);
        $left  ^= self::feistelRoundF($right, $k1);

        $combined = (($left << 16) | $right) & 0xFFFFFFFF;
        $rotAmount = $imm16 & 15;
        return self::rol32($combined, $rotAmount);
    }

    private static function advanceRollingState(int $rollingState, int $pc): int {
        $term = self::u32(0x9E3779B9 * self::u32($pc + 1));
        $mixed = self::u32($rollingState ^ $term);
        return self::u32(self::ror32($mixed, 7) + 0x7F4A7C15);
    }

    private static function postInstRollingMix(int $rollingState, int $canonOp): int {
        $term = self::u32(($canonOp + 1) * 0x85EBCA6B);
        return self::u32($rollingState ^ $term);
    }

    private static function deriveInitialRollingState(string $serverKeyShare16B, int $nonce): int {
        $unpacked = unpack("V4", $serverKeyShare16B);
        // srv_k0 = low32: $unpacked[1], high32: $unpacked[2]
        // srv_k1 = low32: $unpacked[3], high32: $unpacked[4]
        $masterLow32  = (self::CLIENT_KEY_LOW32  ^ $unpacked[1] ^ $unpacked[3]) & 0xFFFFFFFF;
        $masterHigh32 = (self::CLIENT_KEY_HIGH32 ^ $unpacked[2] ^ $unpacked[4]) & 0xFFFFFFFF;
        $folded32 = ($masterLow32 ^ $masterHigh32) & 0xFFFFFFFF;
        return ($folded32 ^ ($nonce & 0xFFFFFFFF) ^ 0xA5A55A5A) & 0xFFFFFFFF;
    }

    private static function packInstruction(int $vOp, int $a, int $b, int $cImm16): int {
        return (
            (($cImm16 & 0xFFFF) << 16) |
            (($b & 0x0F) << 12) |
            (($a & 0x0F) << 8) |
            ($vOp & 0xFF)
        ) & 0xFFFFFFFF;
    }

    /**
     * Biên dịch chuỗi JSON (danh sách Mod Skin / Phối đồ) thành gói Envelope nhị phân Chimera-VM:
     *   [8B Dynamic Opcode Map] + [16B Server Key Share] + [Payload .chmr]
     */
    public static function compileStringToEnvelope(string $rawBytes): string {
        // 1. Sinh ngẫu nhiên 8 V-Opcodes phân biệt trong miền 0x10..0xFE
        $pool = range(0x10, 0xFE);
        shuffle($pool);
        $opcodeMap = array_slice($pool, 0, 8);
        $opcodeMapBin = pack("C8", ...$opcodeMap);

        // 2. Sinh ngẫu nhiên 16-byte Server Key Share và 32-bit Nonce
        $serverKeyShareBin = random_bytes(16);
        $nonce = unpack("V", random_bytes(4))[1] & 0xFFFFFFFF;

        // 3. Nén zlib mức 9 và căn lề 4-byte
        $origSize = strlen($rawBytes);
        $compRaw = zlib_encode($rawBytes, ZLIB_ENCODING_DEFLATE, 9);
        $compSize = strlen($compRaw);
        $padLen = (4 - ($compSize % 4)) % 4;
        $alignedComp = $compRaw . str_repeat("\x00", $padLen);

        $rollingState = self::deriveInitialRollingState($serverKeyShareBin, $nonce);
        $pc = 0;
        $packedInsns = "";
        $insnCount = 0;

        $emitRaw = function(int $canonOp, int $a, int $b, int $cImm16) use (
            &$rollingState, &$pc, &$packedInsns, &$insnCount, $opcodeMap
        ): int {
            $rollingState = self::advanceRollingState($rollingState, $pc);
            $vOp = $opcodeMap[$canonOp];
            $plainInst = self::packInstruction($vOp, $a, $b, $cImm16);
            $encInst = ($plainInst ^ $rollingState) & 0xFFFFFFFF;
            $packedInsns .= pack("V", $encInst);
            $usedState = $rollingState;
            $rollingState = self::postInstRollingMix($rollingState, $canonOp);
            $pc++;
            $insnCount++;
            return $usedState;
        };

        $words = array_values(unpack("V*", $alignedComp));
        $numWords = count($words);

        for ($wordIdx = 0; $wordIdx < $numWords; $wordIdx++) {
            $targetWord = $words[$wordIdx] & 0xFFFFFFFF;

            // Chọn 3 thanh ghi khác nhau trong tập R[0..15]
            $regA = ($wordIdx * 3) & 15;
            $regB = ($regA + 5) & 15;
            $regDst = ($regA + 11) & 15;

            $feistelImm = random_int(0x0101, 0xFFFE);
            $mask16 = random_int(0x0001, 0xFFFE);
            $rorShift = random_int(1, 31);

            $ib2Mode = $wordIdx % 3;
            if ($ib2Mode === 0) {
                $canonAlu = self::CANON_IB2_XOR;
            } elseif ($ib2Mode === 1) {
                $canonAlu = self::CANON_IB2_ADD;
            } else {
                $canonAlu = self::CANON_IB2_ROR;
            }

            // Mô phỏng nháp tiến trình rolling_state qua 5 lệnh đầu để lấy state tại lệnh thứ 6 (CUS_FEISTEL_MIX)
            $simState = $rollingState;
            $simPc = $pc;
            $prelimOps = [
                self::CANON_IB2_LOADK,
                self::CANON_IB2_LOADK,
                self::CANON_IB2_LOADK,
                $canonAlu,
                self::CANON_CUS_PUSH,
            ];
            foreach ($prelimOps as $opCode) {
                $simState = self::advanceRollingState($simState, $simPc);
                $simState = self::postInstRollingMix($simState, $opCode);
                $simPc++;
            }
            $feistelExecState = self::advanceRollingState($simState, $simPc);

            $preFeistelWord = self::feistelEncryptWord32($targetWord, $feistelExecState, $feistelImm);

            if ($canonAlu === self::CANON_IB2_XOR) {
                $regAVal = ($preFeistelWord ^ $mask16) & 0xFFFFFFFF;
                $aluCField = $regB;
            } elseif ($canonAlu === self::CANON_IB2_ADD) {
                $regAVal = ($preFeistelWord - $mask16) & 0xFFFFFFFF;
                $aluCField = $regB;
            } else {
                $regAVal = self::rol32($preFeistelWord, $rorShift);
                $aluCField = $rorShift;
            }

            $low16  = $regAVal & 0xFFFF;
            $high16 = ($regAVal >> 16) & 0xFFFF;

            // 4 lệnh IronBrew2 Register
            $emitRaw(self::CANON_IB2_LOADK, $regA, 0, $low16);
            $emitRaw(self::CANON_IB2_LOADK, $regA, 1, $high16);
            $emitRaw(self::CANON_IB2_LOADK, $regB, 0, $mask16);
            $emitRaw($canonAlu, $regDst, $regA, $aluCField);

            // 3 lệnh Custom Stack
            $emitRaw(self::CANON_CUS_PUSH, $regDst, 0, 0);
            $emitRaw(self::CANON_CUS_FEISTEL_MIX, 0, 0, $feistelImm);
            $emitRaw(self::CANON_CUS_EMIT, 0, 0, 0);
        }

        // Kết thúc bằng CUS_HALT
        $emitRaw(self::CANON_CUS_HALT, 0, 0, 0);

        $flags = self::FLAG_ZLIB_COMPRESSED | self::FLAG_INTERLEAVED_VM;
        $header = pack(
            "a4CCvVVV",
            self::CHIMERA_MAGIC,
            self::CHIMERA_VERSION,
            self::DATA_TYPE_STRING_URL,
            $flags,
            $origSize,
            $compSize,
            $nonce
        );
        $chmrPayload = $header . pack("V", $insnCount) . $packedInsns;

        // Trả về khung nhị phân chuẩn 24-byte prefix + CHMR:
        // [8B Opcode Map][16B Server Key Share][CHMR Payload]
        return $opcodeMapBin . $serverKeyShareBin . $chmrPayload;
    }
}
```