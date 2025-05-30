<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;
use Illuminate\Support\Str;
use League\Csv\Reader;

class RuleReset extends ValidationBase
{
    protected array $rules = [
        'password' => 'required|string|min:10|confirmed|regex:/^[ A-Za-z0-9_@.\/#!?£&+$%^*-]*$/',
    ];

    public function __construct()
    {
    }

    /**
     * Additional checks
     */
    public function additionalChecks($inputs, $email)
    {
        $password = $inputs['password'];

        //password cannot be email
        if (strtolower($email) === strtolower($password)) {
            $this->addAdditionalError('password', 'ec5_36');
        }

        //cannot have "epicollect"
        if (Str::contains(strtolower($password), 'epicollect')) {
            $this->addAdditionalError('password', 'ec5_377');
        }

        //check password is not repetitive
        $reader = Reader::createFromPath(public_path() . '/csv/repetitive-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if ($record[0] === $password) {
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password not sequential
        $reader = Reader::createFromPath(public_path() . '/csv/sequential-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if ($record[0] === $password) {
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password is not a common word
        $reader = Reader::createFromPath(public_path() . '/csv/words-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if ($record[0] === $password) {
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password is not too common
        //list from t.ly/nZNzn
        $reader = Reader::createFromPath(public_path() . '/csv/common-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if ($record[0] === $password) {
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }
    }
}
