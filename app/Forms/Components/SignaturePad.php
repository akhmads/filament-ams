<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * A signature canvas that stores its result as a PNG data URI.
 */
class SignaturePad extends Field
{
    protected string $view = 'forms.components.signature-pad';

    protected int $canvasHeight = 180;

    public function canvasHeight(int $height): static
    {
        $this->canvasHeight = $height;

        return $this;
    }

    public function getCanvasHeight(): int
    {
        return $this->canvasHeight;
    }
}
