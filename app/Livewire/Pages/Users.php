<?php

namespace App\Livewire\Pages;

use App\Models\{User,Role,Module,Permission,ActivityLogs};
use Livewire\{Component,WithPagination};
use Illuminate\Support\Facades\{Auth, Hash,DB};
use Illuminate\Validation\Rule;

class Users extends Component
{
    use WithPagination;

    // Active Tab
    public $activeTab = 'users'; // 'users' or 'roles'

    // Search and Filter
    public $searchTerm = '';
    public $selectedRole = 'all';

    // User Modal States
    public $showUserModal = false;
    public $showDeleteUserModal = false;
    public $showViewUserModal = false;

    // Role Modal States
    public $showRoleModal = false;
    public $showDeleteRoleModal = false;
    public $showPermissionsModal = false;

    // User Form Properties
    public $userId;
    public $name;
    public $username;
    public $email;
    public $role_id;
    public $password;
    public $password_confirmation;
    public $is_active = true;
    public $isEditMode = false;

    // View User Data
    public $viewUser;

    // Role Form Properties
    public $roleId;
    public $roleName;
    public $roleDescription;
    public $isEditRoleMode = false;

    // Permissions Management
    public $selectedRoleId;
    public $selectedRoleName;
    public $permissions = [];
    public $modules = [];

    public function mount()
    {
        $this->modules = Module::all();
    }

    // ========== TAB SWITCHING ==========
    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->searchTerm = '';
    }

    // ========== USER MANAGEMENT METHODS ==========
    
    protected function userRules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($this->userId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->userId),
            ],
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ];

        if (!$this->isEditMode) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } elseif ($this->password) {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function updatingSelectedRole()
    {
        $this->resetPage();
    }

    public function showAddUserModal()
    {
        $this->resetUserForm();
        $this->showUserModal = true;
        $this->isEditMode = false;
    }

    public function showEditUserModal($userId)
    {
        $user = User::findOrFail($userId);
        
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->role_id = $user->role_id;
        $this->is_active = $user->is_active;
        $this->isEditMode = true;
        $this->showUserModal = true;
    }


    public function resetUserPassword($userId)
{
    DB::beginTransaction();
    try {
        $user = User::findOrFail($userId);

        // Generate a temporary password (you can customize this)
        $tempPassword = 'Temp' . rand(1000, 9999) . '!';
        
        $user->update([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        ActivityLogs::create([
            'user_id' => Auth::id(),
            'action_type' => 'password_reset',
            'description' => 'Reset password for user: ' . $user->name,
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'metadata' => json_encode([
                'reset_by' => Auth::user()->name,
                'temporary_password' => $tempPassword, // Store temporarily for admin to see
            ])
        ]);

        DB::commit();
        
        // Flash the temporary password for the admin to communicate to the user
        session()->flash('message', "Password reset successfully. Temporary password: {$tempPassword}");
        session()->flash('temp_password', $tempPassword);
        
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('error', 'An error occurred: ' . $e->getMessage());
    }
}

    public function saveUser()
    {
        $this->validate($this->userRules(), [
        'name.required' => 'The name field is required.',
        'username.required' => 'The username field is required.',
        'username.unique' => 'This username is already taken.',
        'email.unique' => 'This email is already taken.',
        'role_id.required' => 'Please select a role.',
        'password.required' => 'The password field is required.',
        'password.min' => 'Password must be at least 8 characters.',
        'password.confirmed' => 'Password confirmation does not match.',
        ]);

        DB::beginTransaction();
        try {
            $userData = [
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'role_id' => $this->role_id,
                'is_active' => $this->is_active,
            ];

            if ($this->isEditMode) {
                $user = User::findOrFail($this->userId);
                
            if ($this->password) {
                $userData['password'] = Hash::make($this->password);
                $userData['must_change_password'] = true; // Force password change when admin resets
                $userData['password_changed_at'] = null;
            }
                
                $user->update($userData);
                
                ActivityLogs::create([
                    'user_id' => Auth::id(),
                    'action_type' => 'update',
                    'description' => 'Updated user: ' . $user->name,
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'metadata' => json_encode(['updated_fields' => array_keys($userData)])
                ]);

                session()->flash('message', 'User updated successfully.');
            } else {
                $userData['password'] = Hash::make($this->password);
                $userData['must_change_password'] = true;
                $user = User::create($userData);
                
                ActivityLogs::create([
                    'user_id' => Auth::id(),
                    'action_type' => 'create',
                    'description' => 'Created new user: ' . $user->name,
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'metadata' => json_encode([
                        'username' => $user->username,
                        'role' => $user->role->name,
                        'initial_password' => $this->password,
                    ])
                ]);

                session()->flash('message', 'User created successfully.');
            }

            DB::commit();
            $this->closeUserModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function showViewUserModalMethod($userId)
    {
        $this->viewUser = User::with(['role', 'user_logs' => function($query) {
            $query->latest()->limit(10);
        }])->findOrFail($userId);
        
        $this->showViewUserModal = true;
    }

    public function confirmDeleteUser($userId)
    {
        $this->userId = $userId;
        $this->showDeleteUserModal = true;
    }

    public function deleteUser()
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($this->userId);
            
            if ($user->id === Auth::id()) {
                session()->flash('error', 'You cannot delete your own account.');
                $this->closeDeleteUserModal();
                return;
            }

            $userName = $user->name;
            
            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'delete',
                'description' => 'Deleted user: ' . $userName,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'metadata' => json_encode([
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role->name
                ])
            ]);

            $user->delete();
            
            DB::commit();
            session()->flash('message', 'User deleted successfully.');
            $this->closeDeleteUserModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function toggleUserStatus($userId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);
            
            if ($user->id === Auth::id()) {
                session()->flash('error', 'You cannot deactivate your own account.');
                return;
            }

            $user->is_active = !$user->is_active;
            $user->save();

            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'update',
                'description' => ($user->is_active ? 'Activated' : 'Deactivated') . ' user: ' . $user->name,
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'metadata' => json_encode(['status' => $user->is_active ? 'active' : 'inactive'])
            ]);

            DB::commit();
            session()->flash('message', 'User status updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function closeUserModal()
    {
        $this->showUserModal = false;
        $this->resetUserForm();
    }

    public function closeDeleteUserModal()
    {
        $this->showDeleteUserModal = false;
        $this->userId = null;
    }

    public function closeViewUserModal()
    {
        $this->showViewUserModal = false;
        $this->viewUser = null;
    }

    private function resetUserForm()
    {
        $this->userId = null;
        $this->name = '';
        $this->username = '';
        $this->email = '';
        $this->role_id = null;
        $this->password = '';
        $this->password_confirmation = '';
        $this->is_active = true;
        $this->isEditMode = false;
        $this->resetValidation();
    }

    // ========== ROLE MANAGEMENT METHODS ==========

    protected function roleRules()
    {
        return [
            'roleName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($this->roleId),
            ],
            'roleDescription' => 'nullable|string|max:500',
        ];
    }

    public function showAddRoleModal()
    {
        $this->resetRoleForm();
        $this->showRoleModal = true;
        $this->isEditRoleMode = false;
    }

    public function showEditRoleModal($roleId)
    {
        $role = Role::findOrFail($roleId);
        
        $this->roleId = $role->id;
        $this->roleName = $role->name;
        $this->roleDescription = $role->description;
        $this->isEditRoleMode = true;
        $this->showRoleModal = true;
    }

    public function saveRole()
    {
        $this->validate($this->roleRules(), [
            'roleName.required' => 'The role name is required.',
            'roleName.unique' => 'This role name already exists.',
        ]);

        DB::beginTransaction();
        try {
            $roleData = [
                'name' => $this->roleName,
                'description' => $this->roleDescription,
            ];

            if ($this->isEditRoleMode) {
                $role = Role::findOrFail($this->roleId);
                $role->update($roleData);
                
                ActivityLogs::create([
                    'user_id' => Auth::id(),
                    'action_type' => 'update',
                    'description' => 'Updated role: ' . $role->name,
                    'entity_type' => 'role',
                    'entity_id' => $role->id,
                    'metadata' => json_encode($roleData)
                ]);

                session()->flash('message', 'Role updated successfully.');
            } else {
                $role = Role::create($roleData);
                
                // Create default permissions (all false) for all modules
                foreach ($this->modules as $module) {
                    Permission::create([
                        'role_id' => $role->id,
                        'module_id' => $module->id,
                        'can_create' => false,
                        'can_view' => false,
                        'can_modify' => false,
                        'can_delete' => false,
                    ]);
                }
                
                ActivityLogs::create([
                    'user_id' => Auth::id(),
                    'action_type' => 'create',
                    'description' => 'Created new role: ' . $role->name,
                    'entity_type' => 'role',
                    'entity_id' => $role->id,
                    'metadata' => json_encode($roleData)
                ]);

                session()->flash('message', 'Role created successfully. Now assign permissions.');
            }

            DB::commit();
            $this->closeRoleModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function showManagePermissionsModal($roleId)
    {
        $role = Role::findOrFail($roleId);
        $this->selectedRoleId = $roleId;
        $this->selectedRoleName = $role->name;
        
        // Load existing permissions
        $existingPermissions = Permission::where('role_id', $roleId)->get();
        
        $this->permissions = [];
        foreach ($existingPermissions as $permission) {
            $this->permissions[$permission->module_id] = [
                'can_create' => $permission->can_create,
                'can_view' => $permission->can_view,
                'can_modify' => $permission->can_modify,
                'can_delete' => $permission->can_delete,
            ];
        }
        
        $this->showPermissionsModal = true;
    }

    public function updatePermissions()
    {
        DB::beginTransaction();
        try {
            foreach ($this->permissions as $moduleId => $perms) {
                Permission::updateOrCreate(
                    [
                        'role_id' => $this->selectedRoleId,
                        'module_id' => $moduleId
                    ],
                    [
                        'can_create' => $perms['can_create'] ?? false,
                        'can_view' => $perms['can_view'] ?? false,
                        'can_modify' => $perms['can_modify'] ?? false,
                        'can_delete' => $perms['can_delete'] ?? false,
                    ]
                );
            }

            $role = Role::findOrFail($this->selectedRoleId);
            
            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'update',
                'description' => 'Updated permissions for role: ' . $role->name,
                'entity_type' => 'role',
                'entity_id' => $role->id,
                'metadata' => json_encode(['permissions' => $this->permissions])
            ]);

            DB::commit();
            session()->flash('message', 'Permissions updated successfully.');
            $this->closePermissionsModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function togglePermission($moduleId, $permissionType)
    {
        if (!isset($this->permissions[$moduleId])) {
            $this->permissions[$moduleId] = [
                'can_create' => false,
                'can_view' => false,
                'can_modify' => false,
                'can_delete' => false,
            ];
        }
        
        $this->permissions[$moduleId][$permissionType] = !($this->permissions[$moduleId][$permissionType] ?? false);
    }

    public function confirmDeleteRole($roleId)
    {
        $role = Role::withCount('users')->findOrFail($roleId);
        
        if ($role->users_count > 0) {
            session()->flash('error', 'Cannot delete role with active users. Reassign users first.');
            return;
        }

        $this->roleId = $roleId;
        $this->showDeleteRoleModal = true;
    }

    public function deleteRole()
    {
        DB::beginTransaction();
        try {
            $role = Role::findOrFail($this->roleId);
            $roleName = $role->name;
            
            Permission::where('role_id', $role->id)->delete();
            
            ActivityLogs::create([
                'user_id' => Auth::id(),
                'action_type' => 'delete role',
                'description' => 'Deleted role: ' . $roleName,
                'entity_type' => 'role',
                'entity_id' => $role->id,
                'metadata' => json_encode(['role_name' => $roleName])
            ]);

            $role->delete();
            
            DB::commit();
            session()->flash('message', 'Role deleted successfully.');
            $this->closeDeleteRoleModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function closeRoleModal()
    {
        $this->showRoleModal = false;
        $this->resetRoleForm();
    }

    public function closeDeleteRoleModal()
    {
        $this->showDeleteRoleModal = false;
        $this->roleId = null;
    }

    public function closePermissionsModal()
    {
        $this->showPermissionsModal = false;
        $this->selectedRoleId = null;
        $this->selectedRoleName = null;
        $this->permissions = [];
    }

    private function resetRoleForm()
    {
        $this->roleId = null;
        $this->roleName = '';
        $this->roleDescription = '';
        $this->isEditRoleMode = false;
        $this->resetValidation();
    }

    // ========== RENDER METHOD ==========

    public function render()
    {
        if ($this->activeTab === 'users') {
            $query = User::with('role');

            if ($this->searchTerm) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('username', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('role', function($roleQuery) {
                        $roleQuery->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
                });
            }

            if ($this->selectedRole !== 'all') {
                $query->where('role_id', $this->selectedRole);
            }

            $users = $query->paginate(10);
            $roles = Role::all();

            return view('livewire.pages.users', [
                'users' => $users,
                'roles' => $roles,
            ]);
        } else {
            $query = Role::withCount('users');

            if ($this->searchTerm) {
                $query->where(function($q) {
                    $q->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
                });
            }

            $roles = $query->paginate(10);

            return view('livewire.pages.users', [
                'roles' => $roles,
                'modules' => $this->modules,
            ]);
        }
    }
}