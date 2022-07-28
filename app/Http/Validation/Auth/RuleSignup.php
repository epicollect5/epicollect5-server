<?php

namespace ec5\Http\Validation\Auth;

use ec5\Http\Validation\ValidationBase;
use Illuminate\Support\Str;
use League\Csv\Reader;

class RuleSignup extends ValidationBase
{

    //see password regex here https://stackoverflow.com/questions/31539727/laravel-password-validation-rule
    // pass1234# works, at least a letter, a number and a symbol
    protected $rules = [
        'name' => 'required|string|min:3|max:25',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:10|confirmed|regex:/^[ A-Za-z0-9_@.\/#!?£&+$%^*-]*$/',
        'g-recaptcha-response' => 'required'
    ];

    public function __construct()
    {
        $this->messages['name.regex'] = 'ec5_205';
        $this->messages['email.unique'] = 'ec5_375';
    }

    /**
     * Additional checks
     */
    public function additionalChecks($inputs)
    {
        $password = $inputs['password'];
        $email = $inputs['email'];

        //password cannot be email
        if(strtolower($email) === strtolower($password)) {
            $this->addAdditionalError('password', 'ec5_36');
        }

        //cannot have "epicollect"
        if(Str::contains(strtolower($password), 'epicollect')) {
            $this->addAdditionalError('password', 'ec5_377');
        }

        //check password is not repetitive
        $reader = Reader::createFromPath(public_path() . '/csv/repetitive-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if($record[0] === $password){
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password not sequential
        $reader = Reader::createFromPath(public_path() . '/csv/sequential-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if($record[0] === $password){
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password is not a common word
        $reader = Reader::createFromPath(public_path() . '/csv/words-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if($record[0] === $password){
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }

        //check password is not too common
        //list from t.ly/nZNzn
        $reader = Reader::createFromPath(public_path() . '/csv/common-passwords.csv', 'r');
        $records = $reader->getRecords();
        foreach ($records as $offset => $record) {
            if($record[0] === $password){
                $this->addAdditionalError('password', 'ec5_377');
                break;
            }
        }
    }
}
