<?php

namespace ec5\Http\Validation;

use Validator;
use Auth;
use Config;
use Exception;
use Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

abstract class ValidationBase
{

    protected $rules = []; //overwrite in child
    public $errors = [];
    protected $messages = [
        'required' => 'ec5_21',
        'confirmed' => 'ec5_40',
        'unique' => 'ec5_134',
        'min' => 'ec5_43',
        'max' => 'ec5_44',
        'email' => 'ec5_42',
        'in' => 'ec5_29',
        'token' => 'ec5_21',
        'array' => 'ec5_29',
        'date' => 'ec5_79',
        'mimes' => 'ec5_81',
        'regex' => 'ec5_87',
        'required_with' => 'ec5_21',
        'required_if' => 'ec5_21',
        'alpha_num_under_spaces' => 'ec5_205',
        'ec5_no_html' => 'ec5_220',
        'ec5_integer' => 'ec5_27',
        'numeric' => 'ec5_27',
        'ec5_greater_than_field' => 'ec5_28',
        'ec5_max_length' => 'ec5_216',
        'string' => 'ec5_87',
        'integer' => 'ec5_27',
        'boolean' => 'ec5_29',
        'present' => 'ec5_21',
        'ec5_unreserved_name' => 'ec5_235'
    ];

    protected $data = [];

    /**
     * Reset the class errors
     *
     * @return array
     */
    public function resetErrors()
    {
        $this->errors = [];
    }

    /**
     * return the errors array
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * return count of errors array
     *
     * @return boolean
     */
    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    /**
     * Apply rules to data array in.
     * If we want to make sure that the keys in match the rules then check_keys == true
     * We pass $data by reference so that we can modify it, if necessary
     *
     * @param $data
     * @param bool $checkKeys
     * @return bool
     */
    public function validate(&$data, bool $checkKeys = false): bool
    {
        try {
            $this->errors = [];
            $this->data = $data;

            // Check data is an array
            if (!is_array($this->data)) {
                //return error as 'data' should be defined and array
                $this->errors['validation'] = ['ec5_269'];
                return false;
            }

            // Make sure only the keys we defined
            if ($checkKeys) {
                // If we have any missing keys, error
                $missingKeys = array_diff_key($this->rules, $this->data);
                if (count($missingKeys) > 0) {
                    $this->errors['missing_keys'] = ['ec5_60'];
                    return false;
                }
                // If we have any extra keys, remove
                $extraKeys = array_diff_key($this->data, $this->rules);
                if (count($extraKeys) > 0) {
                    $this->data = array_intersect_key($this->data, $this->rules);
                }
            }
            // Add our ec5 custom validation rules
            $this->addCustomRules();
            // make a new validator object
            $v = Validator::make($this->data, $this->rules, $this->messages);
            // check for failure
            if ($v->fails()) {
                $this->errors = array_merge($this->errors, $v->errors()->getMessages());
                return false;
            }
            // Validation pass
            return true;
        } catch (\Exception $e) {
            //catch regex invalid or malformed (preg_match() throwing error)
            if (Str::contains($e->getMessage(), 'preg_match()')) {
                //get input ref of question throwing exception
                reset($this->data);
                $inputRef = key($this->data);
                //logs for debugging
                Log::error('source', ['input_ref' => $inputRef]);
                Log::error('Validation exception', ['message' => $e->getMessage()]);
                Log::error('Validation exception', ['trace' => $e->getTrace()]);
                //set input ref as source so the mobile app will flag that question
                $this->errors[$inputRef] = ['ec5_392'];
                return false;
            }

            //default error 
            $this->errors['validation'] = ['ec5_116'];
            return false;
        }
    }

    /**
     * @param $ref
     * @param $code
     */
    protected function addAdditionalError($ref, $code)
    {
        $this->errors[$ref] = [$code];
    }

    protected function isValidRef($ref)
    {
        $inputRef = (isset($this->data['ref'])) ? $this->data['ref'] : '';
        if (!preg_match("/^{$ref}+_[a-zA-Z0-9]{13}$/", $inputRef)) {
            $this->errors[$inputRef] = ['ec5_243'];
        } else {
            return true;
        }
    }

    /**
     * Add custom rules for validation by extending the Validator
     */
    protected function addCustomRules()
    {

        /* IMPLICIT RULES */
        // We use extendImplicit() because we want these rules to run even when we have empty values

        Validator::extendImplicit('alpha_num_under_spaces', function ($attribute, $value, $parameters) {
            return preg_match('/(^[A-Za-z0-9_\s]*$)+/', $value);
            //return preg_match('/(^[A-Za-z0-9_\s\x{00A0}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFEF}]+$)+/', $value);
        });

        Validator::extendImplicit('ec5_unreserved_name', function ($attribute, $value, $parameters) {

            // Admins/Superadmins can use reserved words
            if (in_array(
                Auth::user()->server_role,
                [Config::get('ec5Strings.superadmin'), Config::get('ec5Strings.admin')]
            )) {
                return true;
            }

            foreach (Config::get('app.reserved_words') as $reservedName) {
                // If $reservedName is contained anywhere, return false
                if (preg_match('/(' . $reservedName . ')/', $value)) {
                    return false;
                }
            }

            return true;
        });

        // No html symbols "<", ">" allowed
        // USE: ec5_no_html
        Validator::extendImplicit('ec5_no_html', function ($attribute, $value, $parameters) {
            // If there are any html chars, return false
            return !preg_match('/(<|>)+/', $value);
        });

        // USE: 'ec5_max_length:max'
        Validator::extendImplicit('ec5_max_length', function ($attribute, $value, $parameters) {
            $maxLength = $parameters[0];
            $length = mb_strlen($value, 'UTF-8');
            return $maxLength >= $length;
        });

        // USE: 'ec5_greater_than_field:field'
        Validator::extend('ec5_greater_than_field', function ($attribute, $value, $parameters, $validator) {
            $minField = $parameters[0];
            $data = $validator->getData();
            $minValue = $data[$minField];
            return $value > $minValue;
        });


        /* EXPLICIT RULES */
        // We use extend() because we don't want these rules to run when we have empty values

        // USE: 'ec5_integer'
        Validator::extend('ec5_integer', function ($attribute, $value, $parameters) {
            // ec5_integer allows any positive or negative integer and integers with leading zeros
            return preg_match('/^[\-]?[0-9]+$/', $value);
        });

        //check slug is unique in projects table but skipping archived projects
        //todo: actually there is a constraint in the db! unique(slug)!!!
        Validator::extend('unique_except_archived', function ($attribute, $value, $parameters) {
            $table = $parameters[0];
            $column = $attribute;

            $isUnique = DB::table($table)
                    ->where($column, $value)
                    ->where('status', '<>', 'archived')
                    ->count() === 0;

            return $isUnique;
        });
    }
}
