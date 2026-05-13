<?php
/**
 * Barcode Generator Helper (Code 128)
 * Uses barcode generation to create linear barcodes from product codes
 */

class BarcodeGenerator {
    private $barcode_dir = __DIR__ . '/../assets/img/barcodes/';
    private $barcode_web_dir = '/aplikasi-kasir-copy/assets/img/barcodes/';
    private const SOURCE_MODULE_SCALE = 6;
    private const SOURCE_BAR_HEIGHT = 102;
    private const SOURCE_TOP_PADDING = 10;
    private const SOURCE_TEXT_HEIGHT = 78;
    private const SOURCE_TEXT_SIZE = 32;
    private const SOURCE_QUIET_ZONE_MODULES = 14;
    
    public function __construct() {
        // Create directory if it doesn't exist
        if (!is_dir($this->barcode_dir)) {
            mkdir($this->barcode_dir, 0755, true);
        }
    }
    
    /**
     * Generate Code 128 Barcode for product
     * Returns array with filename and path, or false on failure
     */
    public function generate($code) {
        if (empty($code)) {
            return false;
        }
        
        // Sanitize code for filename
        $sanitized_code = preg_replace('/[^a-zA-Z0-9-_]/', '_', $code);
        $filename = 'barcode_' . $sanitized_code . '.svg';
        $filepath = $this->barcode_dir . $filename;
        
        // Always build the latest high-resolution barcode SVG. If an older/narrower
        // SVG exists, it will be replaced so existing products become scannable too.
        $svg = $this->createCode128SVG($code);
        
        if ($svg !== false && (!file_exists($filepath) || file_get_contents($filepath) !== $svg) && file_put_contents($filepath, $svg)) {
            return [
                'filename' => $filename,
                'filepath' => $filepath,
                'exists' => false
            ];
        }

        if ($svg !== false && file_exists($filepath)) {
            return [
                'filename' => $filename,
                'filepath' => $filepath,
                'exists' => true
            ];
        }
        
        return false;
    }
    
    /**
     * Generate Code 128 Barcode for product
     * Returns HTML img tag ready for display
     */
    public function generateHTML($code, $width = 640, $height = 150) {
        if (empty($code)) {
            return '';
        }
        
        // Sanitize code for filename
        $sanitized_code = preg_replace('/[^a-zA-Z0-9-_]/', '_', $code);
        $filename = 'barcode_' . $sanitized_code . '.svg';
        $filepath = $this->barcode_dir . $filename;
        
        // Generate or refresh the barcode so old narrow SVG files are replaced.
        $this->generate($code);
        
        // Return img tag with explicit width and height for a long, retail-style barcode.
        // SVG keeps the output sharp at any size, while vertical scaling makes scanning easier.
        if (file_exists($filepath)) {
            $image_src = $this->barcode_web_dir . rawurlencode($filename) . '?v=' . filemtime($filepath);

            return sprintf(
                '<img src="%s" alt="Barcode %s" class="barcode-image barcode-img" width="%d" height="%d" />',
                htmlspecialchars($image_src),
                htmlspecialchars($code),
                $width,
                $height,
                $width,
                $height
            );
        }
        
        return '<div class="barcode-error-text">Barcode Error</div>';
    }
    
    /**
     * Generate barcode SVG using built-in function
     * Creates a Code 128 barcode as SVG
     */
    private function generateBarcodeSVG($code, $filepath) {
        $svg = $this->createCode128SVG($code);
        
        if ($svg !== false) {
            file_put_contents($filepath, $svg);
            return true;
        }
        
        return false;
    }
    
    /**
     * Create Code 128 barcode as SVG
     * Implementation of Code 128 barcode generation with wider modules for scanner compatibility
     */
    private function createCode128SVG($code) {
        // Proper Code 128 Set B codewords with checksum for scanner compatibility.
        $codewords = $this->encodeCode128B($code);
        $patterns = $this->getCode128Patterns();
        
        if (empty($codewords)) {
            return false;
        }
        
        // Centralized large-scale source settings used by products, CRUD, and label pages.
        // The wider module scale keeps Add/Edit-generated barcodes consistent and scannable.
        $module_width = self::SOURCE_MODULE_SCALE;
        $quiet_zone = $module_width * self::SOURCE_QUIET_ZONE_MODULES; // Code 128 recommends at least 10 modules on both sides.
        $barcode_height = self::SOURCE_BAR_HEIGHT;
        $top_padding = self::SOURCE_TOP_PADDING;
        $text_height = self::SOURCE_TEXT_HEIGHT;
        
        // Calculate total width from real Code 128 bar/space module patterns.
        $barcode_width_modules = 0;
        foreach ($codewords as $codeword) {
            if (!isset($patterns[$codeword])) {
                return false;
            }

            $pattern = $patterns[$codeword];
            for ($i = 0; $i < strlen($pattern); $i++) {
                $barcode_width_modules += intval($pattern[$i]);
            }
        }

        $barcode_width = $barcode_width_modules * $module_width;
        $total_width = $barcode_width + (2 * $quiet_zone);
        $total_height = $top_padding + $barcode_height + $text_height;
        $escaped_code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        
        // Create a sharp vector barcode. shape-rendering keeps bar edges crisp when printed.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $total_width . '" height="' . $total_height . '" viewBox="0 0 ' . $total_width . ' ' . $total_height . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Barcode ' . $escaped_code . '">';
        $svg .= '<title>Barcode ' . $escaped_code . '</title>';
        $svg .= '<rect width="' . $total_width . '" height="' . $total_height . '" fill="white"/>';
        $svg .= '<g shape-rendering="crispEdges">';
        
        // Draw alternating black bars and white spaces from each Code 128 codeword pattern.
        $x_position = $quiet_zone;
        foreach ($codewords as $codeword) {
            $pattern = $patterns[$codeword];
            for ($i = 0; $i < strlen($pattern); $i++) {
                $segment_width = intval($pattern[$i]) * $module_width;
                
                if ($i % 2 === 0) {
                    $svg .= '<rect x="' . $x_position . '" y="' . $top_padding . '" width="' . $segment_width . '" height="' . $barcode_height . '" fill="black" stroke="none"/>';
                }

                $x_position += $segment_width;
            }
        }
        $svg .= '</g>';
        
        // Add human-readable text below the bars, as on real retail products.
        $text_x = $total_width / 2;
        $svg .= '<text x="' . $text_x . '" y="' . ($top_padding + $barcode_height + 30) . '" text-anchor="middle" font-size="' . self::SOURCE_TEXT_SIZE . '" font-family="Arial, Helvetica, sans-serif" font-weight="bold" fill="black" text-rendering="geometricPrecision">' . $escaped_code . '</text>';
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Proper Code 128B encoding
     * Encodes string to Code 128 barcode pattern for scanner compatibility
     */
    private function encodeCode128B($code) {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        // Code 128 Set B supports printable ASCII characters 32-126.
        if (!preg_match('/^[\x20-\x7E]+$/', $code)) {
            return false;
        }

        $start_code_b = 104;
        $codewords = [$start_code_b];
        $checksum = $start_code_b;

        for ($i = 0; $i < strlen($code); $i++) {
            $value = ord($code[$i]) - 32;
            $codewords[] = $value;
            $checksum += $value * ($i + 1);
        }

        $codewords[] = $checksum % 103;
        $codewords[] = 106; // Stop code.

        return $codewords;
    }

    /**
     * Code 128 module patterns indexed by codeword value.
     * Each digit is the width of a bar/space segment in modules.
     */
    private function getCode128Patterns() {
        return [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
            '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
            '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
            '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
            '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
            '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
            '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
            '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
            '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
        ];
    }
    
    /**
     * Delete barcode file
     */
    public function delete($filename) {
        $filepath = $this->barcode_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return true;
    }
    
    /**
     * Delete barcode by product code
     */
    public function deleteByCode($code) {
        $sanitized_code = preg_replace('/[^a-zA-Z0-9-_]/', '_', $code);
        $filename = 'barcode_' . $sanitized_code . '.svg';
        return $this->delete($filename);
    }
    
    /**
     * Get barcode filename from product code
     */
    public function getFilename($code) {
        $sanitized_code = preg_replace('/[^a-zA-Z0-9-_]/', '_', $code);
        return 'barcode_' . $sanitized_code . '.svg';
    }
}
?>
