<?php

namespace App\Admin\Repositories;

use App\Models\FollowRecord as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class FollowRecord extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
