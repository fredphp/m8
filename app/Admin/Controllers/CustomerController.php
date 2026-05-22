<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\Customer;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class CustomerController extends AdminController
{

    protected $title= '客服消息';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Customer(), function (Grid $grid) {
            $grid->model()->orderByDesc('id');
            $grid->column('id')->sortable();
            $grid->column('user_id');
            $grid->column('question');
            $grid->column('reply');
            $grid->column('created_at');
//            $grid->column('updated_at')->sortable();

            $grid->disableCreateButton();
            $grid->disableQuickEditButton();
//            $grid->disableEditButton();
            $grid->disableViewButton();
//            $grid->disableBatchDelete();
            $grid->disableDeleteButton();
//            $grid->disableActions();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id');
        
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new Customer(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('question');
            $show->field('reply');
            $show->field('created_at');
            $show->field('updated_at');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Customer(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('question');
            $form->text('reply');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
