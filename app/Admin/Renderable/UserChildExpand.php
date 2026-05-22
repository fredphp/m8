<?php


namespace App\Admin\Renderable;


use App\Models\SustainableAccount;
use App\Models\User;
use App\Models\UserWallet;
use Dcat\Admin\Support\LazyRenderable;
use Dcat\Admin\Widgets\Table;

class UserChildExpand extends LazyRenderable
{

    public function render()
    {
        $id = $this->key;
        $lowerId = getLowerIds($id);
        $lowers = User::whereIn('user_id', $lowerId)->get(['user_id', 'email', 'created_at']);
        foreach ($lowers as $k => $lower) {
            $data[$k]['uid'] = $lower->user_id;
            $data[$k]['email'] = $lower->email;
            $abc = countUserDis($lower->user_id);
            $data[$k]['i'] = $abc['i'];
            $data[$k]['e'] = $abc['e'];
            $data[$k]['d'] = $abc['d'];
            $bibi_account = UserWallet::where(['user_id' => $lower->user_id,'coin_id' => 1])->first();
            $data[$k]['bibi'] = $bibi_account ? $bibi_account->usable_balance + $bibi_account->freeze_balance : 0;
            $c_account = SustainableAccount::where(['user_id' => $lower->user_id,'coin_id' => 1])->first();
            $data[$k]['c'] = $c_account ? $bibi_account->usable_balance + $bibi_account->freeze_balance : 0;
        }
        $titles = [
            'UID',
            '邮箱',
            '入金',
            '出金',
            '净入金',
            '币币资产',
            '合约资产',
        ];

        return Table::make($titles, $data);
    }

}
