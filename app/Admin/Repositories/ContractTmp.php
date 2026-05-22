<?php

namespace App\Admin\Repositories;

use App\Models\ContractTmp as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class ContractTmp extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
