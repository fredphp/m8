<?php

namespace App\Admin\Controllers;

use App\Models\UserSign;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Controllers\AdminController;
// 小白  用户签到
class UserSignController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new UserSign(), function (Grid $grid) {

            $grid->disableDeleteButton();
            $grid->disableRowSelector();
            $grid->disableCreateButton();

            $grid->column('id')->sortable();
            // $grid->column('client_type')->using([1=>'Android',2=>'IOS'])->badge([1=>'info',2=>'success']);
            $grid->column('user_id','用户ID');
            $grid->column('days','签到次数');
            $grid->column('sign_time','签到时间');
            $grid->column('created_at');


            $grid->filter(function (Grid\Filter $filter) {

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
        return Show::make($id, new UserSign(), function (Show $show) {
            $show->field('id');
            $show->field('user_id');
            $show->field('days');
            $show->field('sign_time');
            $show->field('created_at');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new UserSign(), function (Form $form) {
            $form->display('id');
            $form->text('user_id');
            $form->text('days');
            $form->text('sign_time');

        });
    }
}
