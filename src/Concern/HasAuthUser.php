<?php

namespace Novalites\Concern;

use App\Models\User;

trait HasAuthUser
{
    protected  ?User $user = null;

    public  function setAuthUser(User $user)
    {
        $this->user = $user;
    }

    public function flushAuthUser()
    {
        $this->user = null;
    }

    public function user()
    {
        return $this->user;
    }
}
