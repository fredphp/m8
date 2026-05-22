<?php

namespace App\Admin\Repositories;

use App\Models\BankImport as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class BankImport extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;
}
