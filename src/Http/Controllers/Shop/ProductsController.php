<?php

namespace SystemInc\LaravelAdmin\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Storage;
use SystemInc\LaravelAdmin\Gallery;
use SystemInc\LaravelAdmin\Product;
use SystemInc\LaravelAdmin\ProductCategory;
use SystemInc\LaravelAdmin\SimilarProduct;
use SystemInc\LaravelAdmin\Traits\HelpersTrait;
use SystemInc\LaravelAdmin\Validations\ProductValidation;
use Validator;

class ProductsController extends Controller
{
    use HelpersTrait;

    /**
     * Display a listing of the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function getIndex()
    {
        $products = Product::orderBy('id', 'desc')->get();

        return view('admin::products.products', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNew(Request $request)
    {
        $product = new Product();
        $product->title = 'New product';
        $product->url_id = 'new-product-'.time();
        $product->save();

        $gallery = new Gallery();
        $gallery->title = 'product '.$product->id;
        $gallery->key = 'product'.$product->id;
        $gallery->product_id = $product->id;
        $gallery->save();

        $product->gallery_id = $gallery->id;
        $product->save();

        return redirect($request->segment(1).'/shop/products/edit/'.$product->id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $product_id
     *
     * @return \Illuminate\Http\Response
     */
    public function getEdit($product_id)
    {
        $product = Product::find($product_id);

        $products = Product::all();

        $categories = ProductCategory::all();

        return view('admin::products.product', compact('product', 'products', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postSave(Request $request, $product_id)
    {
        // validation
        $validation = Validator::make($request->all(), ProductValidation::rules($product_id), ProductValidation::messages());

        if ($validation->fails()) {
            return back()->withInput()->withErrors($validation);
        }

        $product = Product::find($product_id);
        $product->update($request->all());

        $product->thumb = $this->saveImage($request->file('thumb'), 'products');

        if ($request->input('delete_thumb')) {
            if (Storage::exists($product->thumb)) {
                Storage::delete($product->thumb);
            }
            $product->thumb = null;
        }

        // PDF
        if ($request->file('pdf') && $request->file('pdf')->isValid()) {
            $filename = $request->file('pdf')->getClientOriginalName();
            $dirname = 'pdf';

            // if pdf name exists
            $i = 1;
            while (Storage::exists($dirname.'/'.$filename)) {
                $fileParts = pathinfo($filename);
                $filename = rtrim($fileParts['filename'], '_'.($i - 1))."_$i.".$fileParts['extension'];
                $i++;
            }

            $request->file('pdf')->move(storage_path('app/'.$dirname), $filename);
            $product->pdf = $dirname.'/'.$filename;
        }

        if ($request->input('delete_pdf')) {
            if (Storage::exists($product->pdf)) {
                Storage::delete($product->pdf);
            }
            $product->pdf = null;
        }
        $product->url_id = $this->sanitizeUri($request->url_id);
        $product->save();

        return redirect($request->segment(1).'/shop/products/edit/'.$product->id)->with('success', 'Saved successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $product_id
     *
     * @return \Illuminate\Http\Response
     */
    public function getDelete(Request $request, $product_id)
    {
        $product = Product::find($product_id);

        $gallery = Gallery::whereTitle($product->gallery->title)->first();

        foreach ($gallery->images as $image) {
            Storage::delete($image->source, $image->thumb_source, $image->mobile_source);

            $image->delete();
        }
        Storage::delete($product->pdf);

        $gallery->delete();
        $product->delete();

        return redirect($request->segment(1).'/shop/products/')->with('success', 'Item deleted');
    }

    /**
     * Add similar product.
     *
     * @param Request $request
     * @param int     $product_id
     *
     * @return type
     */
    public function postAddSimilar(Request $request, $product_id)
    {
        $similar_product = SimilarProduct::where(['product_id' => $product_id, 'product_similar_id' => $request->product_similar_id])->first();

        if ($similar_product) {
            return back()->with(['similar' => 'This product exists in similar products']);
        }

        SimilarProduct::create([
            'product_id'         => $product_id,
            'product_similar_id' => $request->product_similar_id,
        ]);

        return back();
    }

    /**
     * Delete similar product.
     *
     * @param int $similar_id
     *
     * @return type
     */
    public function getDeleteSimilar($similar_id)
    {
        SimilarProduct::find($similar_id)->delete();

        return back();
    }
}
