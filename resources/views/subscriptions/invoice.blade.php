<!DOCTYPE html>
<html lang="en">
<style type="text/css">
    .container {
        width: 90%;
        margin-right: auto;
        margin-left: auto;
        padding-right: 10px;
        padding-left: 10px;
        font-family: "BatangChe";
        font-size: 14px;
    }
    h1 {
        font-size: 36px;
    }
    h2 {
        font-size: 28px;
    }
    .top {
        height: 60px;
        margin-top: 20px;
    }

    .top_left_img {
        float: left;
        width: 300px;
    }

    .top_right_inc {
        float: right;
        width: 300px;
    }

    .top_right_inc p {
        color: #b4b4c6;
    }

    .content_title p {
        color: #6B6B75;
        font-size: 16px;
    }

    .row_left {
        width: 35%;
        font-size: 16px;
        color: #9A9A9C;
    }

    .row_right {
        width: 65%;
        font-size: 16px;

    }

    hr {
        border: 1px solid #C4C4C4;
    }

    .content {
        text-align: left;
    }

    .content_transaction {
        width: 100%;
    }
    .item_table .item_name {
        width: 220px;
        font-size: 16px;
        color: #9A9A9C;
        line-height: 16px;
    }
    .item_table .item_value {
        width: 500px;
        font-size: 16px;
        line-height: 16px;
    }
</style>
<body>
    <div class="container">
        <div class="top">
            @if ($data->icon)
            <div class="top_left_img">
                <img height="40" src="var:icon"/>
            </div>
            @endif
        </div>
        <div class="content">
            <div class="content_title">
                <h1><b>Invoice From {{ config('app.name') }}</b></h1>
                <p>Your transaction is completed and processed securely.</p>
                <p>Please retain this copy for your records.</p>
            </div>
            <hr>
            <div class="content_transaction">
                <h2>TRANSACTION</h2>
                <table class="item_table">
                    <tbody>
                        <tr>
                            <td class="item_name">Reference ID</td>
                            <td class="item_value">{{$data->referenceId}}</td>
                        </tr>
                        <tr>
                            <td class="item_name">Amount of payment</td>
                            <td class="item_value">{{$data->amount}}</td>
                        </tr>
                        <tr>
                            <td class="item_name">Date of payment</td>
                            <td class="item_value">{{$data->date}}</td>
                        </tr>
                        <tr>
                            <td class="item_name">Payment account</td>
                            <td class="item_value">{{$data->paymentAccount}}</td>
                        </tr>
                        <tr>
                            <td class="item_name">Package</td>
                            <td class="item_value">{{$data->package}}</td>
                        </tr>
                        @if ($data->subscription->agreement_id) 
                        <tr>
                            <td class="item_name">Expiration time</td>
                            <td class="item_value">{{$data->expirationTime}}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <hr>
            <div class="content_method">
                <h2>PAYMENT METHOD</h2>
                <table class="item_table">
                    <tbody>
                        <tr>
                            <td class="item_name">Method</td>
                            <td class="item_value">Paypal</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <hr>
            <div class="content_customer">
                <h2>CUSTOMER</h2>
                <table class="item_table">
                    <tbody>
                        <tr>
                            <td class="item_name">Name</td>
                            <td class="item_value">{{$data->name}}</td>
                        </tr>
                        <tr>
                            <td class="item_name">Email</td>
                            <td class="item_value">{{$data->email}}</td>
                        </tr>
                        @if ($data->company_name)
                        <tr>
                            <td class="item_name">Company</td>
                            <td class="item_value">{{$data->company_name}}</td>
                        </tr>
                        @endif
                        @if ($data->address)
                        <tr>
                            <td class="item_name">Address</td>
                            <td class="item_value">{{$data->address}}</td>
                        </tr>
                        @endif
                        @if ($data->contact_info)
                        <tr>
                            <td class="item_name">Contact</td>
                            <td class="item_value">{{$data->contact_info}}</td>
                        </tr>
                        @endif
                        @if ($data->website)
                        <tr>
                            <td class="item_name">Website</td>
                            <td class="item_value">{{$data->website}}</td>
                        </tr>
                        @endif
                        @if ($data->tax_no)
                        <tr>
                            <td class="item_name">Tax No.</td>
                            <td class="item_value">{{$data->tax_no}}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
