<?php

namespace App\Admin\Controllers;

use App\Admin\Repositories\UserAddress;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;

class UserAddressController extends AdminController
{
    protected $title = '地址';
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new UserAddress(), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('user_id', '用户ID');
            $grid->column('omni_address');
            $grid->column('eth_address');
            $grid->column('status', '状态')->using([0 => '未分配', 1 => '已分配']);
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
            $grid->disableRowSelector();
//            $grid->disableActions();
            $grid->disableBatchActions();
            $grid->actions(function ($actions) {
                $actions->disableView();
                $actions->disableDelete();
            });
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('user_id');
                $filter->equal('status')->select([0 => '未分配', 1 => '已分配']);
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
        return Show::make($id, new UserAddress(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('omni_address');
            $show->field('eth_address');
            $show->field('status');
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
        return Form::make(new UserAddress(), function (Form $form) {
            $form->display('id');
            $form->text('user_id')->default(0)->required();
            $form->text('omni_address');
            $form->text('eth_address');
            $form->select('status')->options([0 => '未分配',1 => '已分配'])->default(0)->required();

            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
