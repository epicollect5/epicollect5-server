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

        //handle PWA (add-entry)
        //todo: this is useless as after logging out I cannot add or edit an entry?
        if (Str::startsWith(end($parts), 'add-entry')) {
            array_pop($parts);
            $projectSlug = end($parts);
            return redirect()->route('data-editor-add', ['project_slug' => $projectSlug]);
        }
        //todo: handle PWA (edit-entry)
        //I guess this is not needed
        // if (Str::startsWith(end($parts), 'edit-entry')) {
        //     array_pop($parts);
        //     $projectSlug = end($parts);
        //     return redirect()->route('data-editor-edit', ['project_slug' => $projectSlug]);
        // }

        return redirect()->route('home');
    }
}
