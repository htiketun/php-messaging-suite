<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Responses\JsonResponder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        // Issue token if using Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;
        return JsonResponder::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'User registered successfully');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();

        // Issue token if using Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return JsonResponder::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'User logged in successfully');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return JsonResponder::success($user, 'Profile updated successfully');
    }

    public function changePassword(Request $request)
    {
        $user = $request->user()->fresh();

        $request->validate([
            'password' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);
        if (!Hash::check($request->password, $user->password)) {
            return JsonResponder::error('The provided current password is incorrect.', 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return JsonResponder::success(null, 'Password changed successfully');
    }

    public function syncSavedMessages(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'messages' => 'required',
        ]);

        $messages = $request->messages;
        if (is_string($messages)) {
            $messages = json_decode($messages, true);
        }

        if (!is_array($messages)) {
            return JsonResponder::error('Invalid messages format.', 422);
        }

        $path = 'saved_messages/' . $user->id . '.json';
        Storage::disk('local')->put($path, json_encode($messages));

        return JsonResponder::success(null, 'Messages synced and saved successfully');
    }

    public function getSyncedSavedMessages(Request $request)
    {
        $user = $request->user();
        $path = 'saved_messages/' . $user->id . '.json';

        if (Storage::disk('local')->exists($path)) {
            $messages = json_decode(Storage::disk('local')->get($path), true);
        } else {
            $messages = [];
        }

        return JsonResponder::success(['messages' => $messages], 'Fetched synced messages successfully');
    }

    public function syncToDoList(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'todos_data' => 'required',
        ]);

        $todos = $request->todos_data;
        if (is_string($todos)) {
            $todos = json_decode($todos, true);
        }

        if (!is_array($todos)) {
            return JsonResponder::error('Invalid todos format.', 422);
        }

        $path = 'todo_list/' . $user->id . '.json';
        Storage::disk('local')->put($path, json_encode($todos));

        return JsonResponder::success(null, 'To-Do list synced and saved successfully');
    }

    public function getSyncedToDoList(Request $request)
    {
        $user = $request->user();
        $path = 'todo_list/' . $user->id . '.json';

        if (Storage::disk('local')->exists($path)) {
            $todos = json_decode(Storage::disk('local')->get($path), true);
        } else {
            $todos = [];
        }

        return JsonResponder::success(['todos' => $todos], 'Fetched synced to-do list successfully');
    }
}
