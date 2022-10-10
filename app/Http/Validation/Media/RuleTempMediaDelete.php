<?php

namespace ec5\Http\Validation\Media;

use ec5\Http\Validation\ValidationBase;
use Log;
use Illuminate\Support\Str;
use ec5\Libraries\Utilities\Strings;

class RuleTempMediaDelete extends ValidationBase
{
    /**
     * @var array
     */
    protected $rules = [
        'type' => 'required|string|in:delete',
        'id' => 'required|string|min:36|max:36',
        'delete' => 'required',
        'delete.filename' => 'required|string|min:51|max:51',
    ];

    protected $messages = [
        'in' => 'ec5_29',
        'required' => 'ec5_20',
        'min' => 'ec5_28',
        'max' => 'ec5_28',
    ];

    public function additionalChecks($data)
    {
        //check uuid is in correct format
        if (!Strings::isValidUuid($data['id'])) {
            $this->errors['temp-media-delete'] = ['ec5_334'];
            return;
        }

        //check filename is in correct format
        // like "b1e81491-67ae-4aa6-97a0-fe2067a9db07_1665158927.jpg
        // {uuid}_{timestamp}.jpg
        if (!Str::startsWith($data['delete']['filename'], $data['id'])) {
            $this->errors['temp-media-delete'] = ['ec5_87'];
            return;
        }
    }
}
