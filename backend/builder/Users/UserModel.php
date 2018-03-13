<?php namespace Builder\Users;

use Cartalyst\Sentry\Users\Eloquent\User as SentryUser;

class UserModel extends SentryUser {

    protected $table = 'users';

   /**
     * Returns the relationship between users and groups.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(static::$groupModel, static::$userGroupsPivot, 'user_id');
    }

 	public function projects()
    {
        return $this->belongsToMany('Builder\Projects\ProjectModel', 'users_projects', 'user_id', 'project_id');
    }

    /**
     * Allow multiple PCs to be logged in to same account.
     * 
     * @return string
     */
    public function getPersistCode()
    {
        if ( ! $this->persist_code)
        {
            $this->persist_code = $this->getRandomString();

            // Our code got hashed
            $persistCode = $this->persist_code;

            $this->save();

            return $persistCode;            
        } 

        return $this->persist_code;
    }

}