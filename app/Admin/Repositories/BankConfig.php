<?php

namespace App\Admin\Repositories;

use App\Models\BankConfig as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class BankConfig extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
