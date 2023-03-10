<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\MassDestroyUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Org;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        abort_if(!auth()->user()->can('read-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $inputSearchString = $request->input('s', '');

        $users = User::query()
            ->when($inputSearchString, function ($query) use ($inputSearchString) {
                $query->where(function ($query) use ($inputSearchString) {
                    $query->orWhere('name', 'LIKE', '%' . $inputSearchString . '%');
                    $query->orWhere('username', 'LIKE', '%' . $inputSearchString . '%');
                    $query->orWhere('email', 'LIKE', '%' . $inputSearchString . '%');
                    $query->orWhere('phone', 'LIKE', '%' . $inputSearchString . '%');
                    $query->orWhere(function($query) use ($inputSearchString){
                        $query->whereHas('org', function (Builder $builder) use ($inputSearchString) {
                            $builder->orWhere('orgs.name', 'LIKE', '%' . $inputSearchString . '%');
                        });
                        $query->whereHas('role', function (Builder $builder) use ($inputSearchString) {
                            $builder->orWhere('roles.name', 'LIKE', '%' . $inputSearchString . '%');
                        });
                    });
                });
            })
            ->isActive()
            ->with('org:id,name')
            ->with('role:id,name')
            ->orderBy('name')
            ->paginate(config('app-config.datatable_default_row_count'));

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_if(!auth()->user()->can('create-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $org = Org::query()
            ->select('id', 'name')
            ->isActive()
            ->orderBy('id', 'DESC')
            ->get();

        $role = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('users.create', compact('org', 'role'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserRequest $request)
    {
        abort_if(!auth()->user()->can('create-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $data = $request->validated();

        $data['password'] = Hash::make($request->password);
        $data['status'] = 'active';

        $user = User::create($data);

        $role = Role::findById($request->input('role_id'));
        // assign role to user
        $user->assignRole($role->name);

        toast(__('global.crud_actions', ['module' => 'User', 'action' => 'created']), 'success');
        return redirect()->route('admin.users.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        abort_if(!auth()->user()->can('show-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->load('org:id,name');
        $user->load('role:id,name');

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        abort_if(!auth()->user()->can('update-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $role = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('users.edit', compact('user', 'role'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        abort_if(!auth()->user()->can('update-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->update($request->validated());


        toast(__('global.crud_actions', ['module' => 'User', 'action' => 'updated']), 'success');
        return redirect()->route('admin.users.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {

        abort_if(!auth()->user()->can('delete-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->delete();

        toast(__('global.crud_actions', ['module' => 'User', 'action' => 'deleted']), 'success');
        return back();
    }

    /**
     *
     */

    public function massDestroy(MassDestroyUserRequest $request)
    {
        abort_if(!auth()->user()->can('delete-user'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        User::whereIn('id', $request->input('ids'))
            ->update([
                'is_active' => 3,
                'updatedby_userid' => auth()->user()->id,
            ]);

        toast(__('global.crud_actions', ['module' => 'User', 'action' => 'deleted']), 'success');
        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function generateUserName($user_count){
        return "PRCS".($user_count+1);
    }


}
