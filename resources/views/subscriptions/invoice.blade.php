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
            <div class="top_left_img">
                <img height="40" src="data:image/png;base64,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" alt="">
            </div>
            <!--<div class="top_right_inc">
                <p>onlineadspyer.Inc</p>
                <p>LUCKY CENTRE 171 WANCHAI ROAD</p>
                <P>Chai Wan,Hong Kong Island.</P>
            </div>-->
        </div>
        <div class="content">
            <div class="content_title">
                <h1><b>Invoice From Onlineadspyer</b></h1>
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
                        <tr>
                            <td class="item_name">Expiration time</td>
                            <td class="item_value">{{$data->expirationTime}}</td>
                        </tr>
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