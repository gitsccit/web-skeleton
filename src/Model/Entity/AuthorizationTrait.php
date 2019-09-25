<?php

namespace Skeleton\Model\Entity;

trait AuthorizationTrait
{
    /**
     * @param User $user
     * @return bool
     */
    public function isViewableBy(User $user)
    {
        if (isset($this->user)) {
            return $this->user->id === $user->id;
        }

        return $this->user_id === $user->id;
    }

    /**
     * @param User|null $user
     * @return bool
     */
    public function isCreatableBy($user)
    {
        return $this->user_id === $user->id;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isEditableBy(User $user)
    {
        if (isset($this->user)) {
            return $this->user->id === $user->id;
        }

        return $this->user_id === $user->id;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isDeletableBy(User $user)
    {
        if (isset($this->user)) {
            return $this->user->id === $user->id;
        }

        return $this->user_id === $user->id;
    }
}