<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use App\Models\Product;
use App\Models\Cart;
use Srmklive\PayPal\Traits\PayPalRequest; // Import the PayPalRequest trait
use Srmklive\PayPal\Services\PayPal as ExpressCheckout; // Import the correct PayPal class

class PaypalController extends Controller
{
    use PayPalRequest;

    protected $paypalOptions = [];

    /**
     * Set API credentials using the trait's method.
     *
     * @param array $credentials
     */
    public function setApiCredentials(array $credentials): void
    {
        $this->setApiCredentialsTrait($credentials);
    }

    public function payment(Request $request)
    {
        $cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->get()->toArray();

        $data = [];

        $data['items'] = array_map(function ($item) {
            $product = Product::find($item['product_id']);
            return [
                'name' => $product->title,
                'price' => $item['price'],
                'desc' => 'Thank you for using PayPal',
                'qty' => $item['quantity']
            ];
        }, $cart);

        $data['invoice_id'] = 'ORD-' . strtoupper(uniqid());
        $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
        $data['return_url'] = route('success');
        $data['cancel_url'] = route('cancel');

        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $item['price'] * $item['qty'];
        }

        $data['total'] = $total;
        if (session('coupon')) {
            $data['shipping_discount'] = session('coupon')['value'];
        }
        Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => session()->get('id')]);

        $provider = new PayPalClient;

        // Obtain access token
// Obtain access token
$accessToken = $provider->getAccessToken();

// Debugging statement
// dd($accessToken);

// Check if $accessToken is an array and has the 'access_token' key
if (is_array($accessToken) && isset($accessToken['access_token'])) {
    // Extract the actual access token value
    $actualAccessToken = $accessToken['access_token'];

    // Set request headers
    $provider->setRequestHeader('Authorization', 'Bearer ' . $actualAccessToken);

    // Use createOrder for newer versions
    $response = $provider->createOrder([
        'intent' => 'CAPTURE',
        'application_context' => [
            'return_url' => route('success'),
            'cancel_url' => route('cancel'),
        ],
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $data['total'],
                ],
            ],
        ],
    ]);

    // Check the response structure
    // dd(json_encode($response));

    // Assuming the approval_url is present in the response
    $approvalUrl = $response['links'][1]['href'] ?? '';

    return redirect($approvalUrl);
} else {
    // Handle the case where $accessToken is not an array or does not have the 'access_token' key
    // You may want to log an error or return an appropriate response
    return response()->json(['error' => 'Invalid access token'], 400);
}


        // The rest of your code...
    }

    public function success(Request $request)
    {
        // Assuming the approval_url is present in the response
        $token = $request->token;
    
        $provider = new PayPalClient;
    
        // Obtain access token
        $accessToken = $provider->getAccessToken();
    
        // dd($accessToken);
        // Set request headers
        $provider->setRequestHeader('Authorization', 'Bearer ' . $accessToken['access_token']);
    
        // Use getOrder for newer versions
        $response = $provider->capturePaymentOrder($request->token);
    
        // Check the response structure
        // dd($response);
    
        if ($response['status'] === 'COMPLETED') {
            // Order is completed
            request()->session()->flash('success', 'You successfully paid with PayPal! Thank you');
            session()->forget('cart');
            session()->forget('coupon');
            return redirect()->route('home');
        }
    
        request()->session()->flash('error', 'Something went wrong, please try again!!!');
        return redirect()->back();
    }

    public function cancel()
    {
        return "Payment is cancelled.";
    }
}
