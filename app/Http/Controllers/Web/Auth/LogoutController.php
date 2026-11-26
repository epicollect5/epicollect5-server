<?php

namespace ec5\Http\Controllers\Web\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LogoutController extends AuthController
{
    /*
    | This controller handles the logout action.
    */

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        $backlink = url()->previous();
        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();

        //if we are logging out from the dataviewer, send user back there
        // 1 - private project -> login + dataviewer
        // 2 - public project -> dataviewer (without add entry button)
        $parts = explode('/', $backlink ?? '');
        //check for dataviewer url segments
        if (end($parts) === 'data' || end($parts) === 'data?restore=1') {
            array_pop($parts);
            $projectSlug = end($parts);
            return redirect()->route('dataviewer', ['project_slug' => $projectSlug]);
        }

        //handle add/edit entry pages (PWA or legacy data editor, depending on PWA_ENABLED)
        $lastPart = (string) end($parts);
        if (Str::startsWith($lastPart, 'add-entry') || Str::startsWith($lastPart, 'edit-entry')) {
            array_pop($parts);
            $projectSlug = end($parts);
            $routeName = Str::startsWith($lastPart, 'add-entry') ? 'data-editor-add' : 'data-editor-edit';
            return redirect()->route($routeName, ['project_slug' => $projectSlug]);
        }

        return redirect()->route('home');
    }
}
