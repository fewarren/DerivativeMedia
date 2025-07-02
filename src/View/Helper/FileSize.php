<?php declare(strict_types=1);

namespace DerivativeMedia\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for formatting file sizes in human-readable format.
 */
class FileSize extends AbstractHelper
{
    /**
     * Converts a file size in bytes to a human-readable string with appropriate units.
     *
     * Returns "0 bytes" if the input is null, zero, or negative. For positive values, the size is converted to the largest suitable unit (bytes, KB, MB, etc.) using a divisor of 1000. Decimal places are shown for units above bytes only if the value is less than 10 and precision is greater than zero.
     *
     * @param int|null $bytes The file size in bytes.
     * @param int $precision Number of decimal places for units above bytes (default: 1).
     * @return string The formatted file size string.
     */
    public function __invoke($bytes = null, int $precision = 1): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 bytes';
        }

        $bytes = (int) $bytes;
        
        if ($bytes < 0) {
            return '0 bytes';
        }

        // Units array matching the JavaScript implementation
        $units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        $unitIndex = 0;
        $size = $bytes;
        
        // Convert to appropriate unit (using 1000 as divisor to match JavaScript)
        while ($size >= 1000 && $unitIndex < count($units) - 1) {
            $size = $size / 1000;
            $unitIndex++;
        }
        
        // Format the number
        if ($unitIndex === 0) {
            // For bytes, don't show decimal places
            $formattedSize = number_format($size, 0);
        } else {
            // For other units, show decimal places only if needed
            $formattedSize = $size < 10 && $precision > 0 
                ? number_format($size, $precision) 
                : number_format($size, 0);
        }
        
        return $formattedSize . ' ' . $units[$unitIndex];
    }
}
