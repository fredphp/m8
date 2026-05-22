<?php

namespace App\Admin\Repositories;

use App\Models\FollowPlan as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class FollowPlan extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
