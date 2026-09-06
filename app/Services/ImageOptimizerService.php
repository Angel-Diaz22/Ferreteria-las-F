<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * ============================================================================
 * SERVICIO: ImageOptimizerService (Compresión y Conversión a WebP)
 * ============================================================================
 * ¿POR QUÉ ES VITAL ESTE SERVICIO EN UN SISTEMA POS?
 * Los empleados de una ferretería suelen tomar fotos con sus celulares modernos,
 * las cuales pesan entre 3 MB y 10 MB cada una (en formatos pesados como JPG/PNG).
 *
 * Si un catálogo tiene 3.000 productos y cada imagen pesa 5 MB:
 * 1. El servidor se queda sin espacio rápidamente.
 * 2. La pantalla del Punto de Venta se vuelve lenta al buscar productos.
 *
 * Este servicio toma cualquier imagen subida, la redimensiona a un tamaño óptimo
 * para catálogo (máximo 1000px de ancho) y la convierte al formato WEBP de Google,
 * reduciendo el peso en un 90% (~60 KB) sin perder calidad visible.
 */
class ImageOptimizerService
{
    /**
     * Optimiza y convierte una imagen al formato WebP.
     *
     * @param  string  $relativePath  Ruta relativa en el disco 'public' (ej: 'products/foto.jpg')
     * @param  int  $maxWidth  Ancho máximo permitido en píxeles (default: 1000px)
     * @param  int  $quality  Calidad de compresión WebP de 0 a 100 (default: 80)
     * @return string Ruta relativa de la nueva imagen .webp
     */
    public static function convertToWebp(string $relativePath, int $maxWidth = 1000, int $quality = 80): string
    {
        // 1. Obtener la ruta absoluta en el sistema de archivos del Mac/Servidor
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! file_exists($absolutePath)) {
            return $relativePath;
        }

        // 2. Obtener información de la imagen (dimensiones y formato MIME)
        $imageInfo = @getimagesize($absolutePath);
        if (! $imageInfo) {
            return $relativePath; // No es una imagen válida, retornamos tal cual
        }

        [$width, $height, $imageType] = $imageInfo;

        // 3. Crear el recurso GD en memoria según el tipo original
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolutePath),
            default => null,
        };

        if (! $sourceImage) {
            return $relativePath;
        }

        // 4. Calcular nuevas dimensiones proporcionales si excede el ancho máximo
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round(($height * $maxWidth) / $width);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // 5. Crear lienzo vacío con las dimensiones finales
        $targetImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar canal alfa (transparencia) si proviene de un PNG
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);

        // Copiar y redimensionar con suavizado de alta calidad (resampling)
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        // 6. Generar nuevo nombre con extensión .webp
        $pathInfo = pathinfo($relativePath);
        $webpRelativePath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'.webp';
        $webpAbsolutePath = Storage::disk('public')->path($webpRelativePath);

        // 7. Guardar como WebP en disco
        imagewebp($targetImage, $webpAbsolutePath, $quality);

        // 8. Liberar memoria RAM ocupada por GD
        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        // 9. Si el original no era .webp, eliminamos el archivo pesado anterior
        if (strtolower($pathInfo['extension'] ?? '') !== 'webp') {
            @unlink($absolutePath);
        }

        return $webpRelativePath;
    }
}
