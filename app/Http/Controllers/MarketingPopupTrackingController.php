<?php

namespace App\Http\Controllers;

use App\Models\MarketingPopup;
use Illuminate\Http\Request;

class MarketingPopupTrackingController extends Controller
{
    public function impression(Request $request, MarketingPopup $popup)
    {
        $request->validate(['analytics_consent' => ['required', 'accepted']]);
        abort_unless($this->isTrackable($popup), 404);
        $popup->increment('impressions');

        return response()->noContent();
    }

    public function click(Request $request, MarketingPopup $popup)
    {
        $request->validate(['analytics_consent' => ['required', 'accepted']]);
        abort_unless($this->isTrackable($popup), 404);
        $popup->increment('clicks');

        return response()->noContent();
    }

    private function isTrackable(MarketingPopup $popup): bool
    {
        return $popup->status === 'active'
            && (! $popup->starts_at || $popup->starts_at->lte(now()))
            && (! $popup->ends_at || $popup->ends_at->gte(now()));
    }
}
