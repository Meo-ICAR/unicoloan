<?php

namespace App\Http\Controllers;

use App\Filament\Agenti\Pages\SimulazioneFinanziamento;
use App\Support\Agenti\AgentiDevBypass;
use App\Support\Agenti\DevCampanellaIdentity;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AgentiDevLoginController extends Controller
{
    public function __invoke(): RedirectResponse|Response
    {
        AgentiDevBypass::abortUnlessEnabled();

        $user = DevCampanellaIdentity::user();

        if ($user === null || ! $user->canAccessPanel(Filament::getPanel('agenti'))) {
            return response(
                'Identità dev Campanella non predisposta. Esegui: php artisan agenti:ensure-dev-campanella',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        Auth::login($user);

        return redirect()->to(SimulazioneFinanziamento::getUrl(panel: 'agenti'));
    }
}
