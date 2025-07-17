<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function order(Request $request)
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect('/products');
        }

        $items = [];
        $totalPrice = 0;

        foreach ($cart as $productId => $quantity) {
            $product = Product::find($productId);
            $totalPrice += $product->price * $quantity;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
            ];
        }

        try {
            DB::beginTransaction();
            $order = new Order;
            $order->user_id = $request->user()->id;
            $order->total_price = $totalPrice;
            $order->save();

            foreach ($items as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];

                $orderDetail = new OrderDetail;
                $orderDetail->order_id = $order->id;
                $orderDetail->product_id = $product->id;
                $orderDetail->price = $product->price;
                $orderDetail->quantity = $quantity;
                $orderDetail->save();

                $stock = $product->stock;
                $stock -= $quantity;
                if ($stock < 0) {
                    throw new Exception('在庫がないです');
                }
                $product->stock = $stock;
                $product->save();
            }

            session()->forget('cart');

            echo '注文しました';
            DB::commit();
        } catch (Exception $e) {
            echo 'エラーが起こりました<br>';
            echo $e->getMessage();
            DB::rollBack();
        }
    }
}
