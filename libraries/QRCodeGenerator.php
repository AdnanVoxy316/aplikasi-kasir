<?php
/**
 * QR Code Generator Helper
 * Uses QR Server API to generate QR codes and stores them locally
 */

class QRCodeGenerator {
    private $qrcode_dir = 'assets/img/qrcodes/';
    
    public function __construct() {
        // Create directory if it doesn't exist
        if (!is_dir($this->qrcode_dir)) {
            mkdir($this->qrcode_dir, 0755, true);
        }
    }
    
    /**
     * Generate QR Code for product
     * @param string $code Product code
     * @return array|false Returns array with filename and path, or false on failure
     */
    public function generate($code) {
        if (empty($code)) {
            return false;
        }
        
        // Generate filename based on product code
        $filename = 'qr_' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $code) . '.png';
        $filepath = $this->qrcode_dir . $filename;
        
        // If QR code already exists, return it
        if (file_exists($filepath)) {
            return [
                'filename' => $filename,
                'filepath' => $filepath,
                'exists' => true
            ];
        }
        
        // Generate QR code using QR Server API
        $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/';
        $params = [
            'size' => '300x300',
            'data' => $code,
            'format' => 'png'
        ];
        
        $url = $qr_api_url . '?' . http_build_query($params);
        
        try {
            // Download QR code image
            $qr_image = @file_get_contents($url, false, stream_context_create([
                'ssl' => ['verify_peer' => false]
            ]));
            
            if ($qr_image === false) {
                // Fallback: Create local QR code if API fails
                return $this->generateLocal($code, $filepath, $filename);
            }
            
            // Save QR code image
            if (file_put_contents($filepath, $qr_image)) {
                return [
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'exists' => false
                ];
            }
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Generate QR Code locally as fallback
     */
    private function generateLocal($code, $filepath, $filename) {
        // Create a simple placeholder QR code image
        // This is a fallback if API is unavailable
        $width = 300;
        $height = 300;
        
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        
        // Fill with white
        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        
        // Add border
        imagerectangle($image, 10, 10, $width - 10, $height - 10, $black);
        
        // Add code text in center
        $text = $code;
        $font = 5;
        $textColor = $black;
        $textWidth = strlen($text) * imagefontwidth($font);
        $x = ($width - $textWidth) / 2;
        $y = ($height - imagefontheight($font)) / 2;
        
        imagestring($image, $font, $x, $y, $text, $textColor);
        
        // Add placeholder pattern
        for ($i = 0; $i < 10; $i++) {
            $rx = rand(30, $width - 30);
            $ry = rand(30, $height - 30);
            imagefilledrectangle($image, $rx, $ry, $rx + 20, $ry + 20, $gray);
        }
        
        // Save image
        if (imagepng($image, $filepath)) {
            imagedestroy($image);
            return [
                'filename' => $filename,
                'filepath' => $filepath,
                'exists' => false
            ];
        }
        
        imagedestroy($image);
        return false;
    }
    
    /**
     * Delete QR Code
     */
    public function delete($filename) {
        $filepath = $this->qrcode_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return true;
    }
    
    /**
     * Delete QR Code by product code
     */
    public function deleteByCode($code) {
        $filename = 'qr_' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $code) . '.png';
        return $this->delete($filename);
    }
    
    /**
     * Get QR Code filename from product code
     */
    public function getFilename($code) {
        return 'qr_' . preg_replace('/[^a-zA-Z0-9-_]/', '_', $code) . '.png';
    }
}
?>
