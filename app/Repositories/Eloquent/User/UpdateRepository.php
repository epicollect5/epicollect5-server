<?php namespace ec5\Repositories\Eloquent\User;

trait UpdateRepository {

    /**
     * Function for finding, validating and updating
     * a user via superadmin/admin account.
     * Will add errors appropriately if any occur.
     *
     * @param $user
     * @param $field
     * @param $value
     * @return bool
     */
    public function updateUserByAdmin($user, $field, $value)
    {

        // check valid user and not super admin
        if ($user && !$user->isSuperAdmin()) {

            // check this field is a valid property
            if (isset($user->$field)) {

                // set the required field to the supplied value
                $user->$field = $value;
                // attempt to save
                if ($user->save()) {
                    return true;
                }

            } else {
                // user not found
                $this->errors = ['ec5_48'];
            }

        } else {
            // could not update user
            $this->errors = ['ec5_34'];
        }

        return false;

    }

    /**
     * Function to update a user
     *
     * @param $userId
     * @param array $data
     * @return bool
     */
    public function updateById($userId, $data = [])
    {

        $user = $this->where('id', '=', $userId);

        foreach ($data as $key => $value) {
            $user->$key = $value;
        }

        // attempt to save
        if ($user->save()) {
            return true;
        }

        $this->errors = ['ec5_49'];
        return false;

    }


}