<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

/**
 * Where the QR on an asset label points. Sends the scanner to the asset's
 * detail page in the admin panel; a signed-out user is asked to log in first.
 */
class AssetLookupController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        $asset = Asset::query()->where('code', $code)->firstOrFail();

        return redirect()->to(AssetResource::getUrl('view', ['record' => $asset], panel: 'admin'));
    }
}
