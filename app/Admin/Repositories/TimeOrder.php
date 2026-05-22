<?php

namespace App\Admin\Repositories;

use App\Models\TimeOrder as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class TimeOrder extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
