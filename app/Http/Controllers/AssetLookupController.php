<?php

namespace App\Http\Controllers;

use App\Enums\AssetAuditStatus;
use App\Exceptions\AssetAuditException;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Filament\Resources\AssetAudits\Pages\ScanAssetAudit;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetAuditService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Where the QR on an asset label points. Sends the scanner to the asset's
 * detail page in the admin panel; a signed-out user is asked to log in first.
 *
 * While the signed-in user is counting a room in an audit, the scan is recorded
 * in that audit instead and the user returns to the scan page.
 */
class AssetLookupController extends Controller
{
    public function __invoke(string $code, AssetAuditService $audits): RedirectResponse
    {
        $asset = Asset::query()->where('code', $code)->firstOrFail();

        $counting = $this->roomBeingCounted();

        if ($counting === null) {
            return redirect()->to(AssetResource::getUrl('view', ['record' => $asset], panel: 'admin'));
        }

        [$audit, $room, $user] = $counting;

        try {
            $line = $audits->scan($audit, $asset->code, $room, $user);

            Notification::make()->title("{$asset->code} recorded")->body($line->scanSummary())->success()->send();
        } catch (AssetAuditException $exception) {
            Notification::make()->title('Not recorded')->body($exception->getMessage())->danger()->send();
        }

        return redirect()->to(AssetAuditResource::getUrl('scan', ['record' => $audit], panel: 'admin'));
    }

    /**
     * The audit and room the signed-in user chose on the scan page, while that
     * audit is still counting and the user may still scan for it.
     *
     * @return array{0: AssetAudit, 1: Location, 2: User}|null
     */
    private function roomBeingCounted(): ?array
    {
        $user = Auth::user();
        $remembered = session(ScanAssetAudit::SESSION_KEY);

        if (! $user instanceof User || ! is_array($remembered)) {
            return null;
        }

        $audit = AssetAudit::query()->find($remembered['audit'] ?? null);
        $room = Location::query()->find($remembered['location'] ?? null);

        if ($audit === null || $room === null || $audit->status !== AssetAuditStatus::InProgress || ! $user->can('update', $audit)) {
            return null;
        }

        return [$audit, $room, $user];
    }
}
