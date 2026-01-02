<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use WeiJuKeJi\PaymentBill\Http\Controllers\Concerns\RespondsWithApi;

abstract class Controller extends BaseController
{
    use RespondsWithApi;
}
