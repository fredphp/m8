<?php

namespace App\Admin\Repositories;

use App\Models\UserAddress as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class UserAddress extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
