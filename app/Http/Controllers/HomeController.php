<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function adminTest()
    {
        return response()->json(['data' => 'You are admin!']);
    }

    public function userTest()
    {
        return response()->json(['data' => 'You are user!']);
    }
}
