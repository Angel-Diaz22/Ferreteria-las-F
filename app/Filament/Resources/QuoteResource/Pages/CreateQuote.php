<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Models\Quote;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    /**
     * HOOK mutateFormDataBeforeCreate():
     * Se ejecuta antes de insertar el registro en la tabla `quotes`.
     * Garantiza que el usuario autenticado sea el asesor responsable
     * y asegura un consecutivo único consecutivo COT-XXXXX.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id() ?? $data['user_id'] ?? 1;

        if (empty($data['quote_number'])) {
            $data['quote_number'] = DB::transaction(function () {
                $maxId = Quote::lockForUpdate()->max('id') ?? 0;
                $nextNumber = $maxId + 1;
                do {
                    $quoteNumber = 'COT-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
                    $nextNumber++;
                } while (Quote::where('quote_number', $quoteNumber)->exists());

                return $quoteNumber;
            });
        }

        return $data;
    }
}
