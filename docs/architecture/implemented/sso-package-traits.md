# SSO Package: Scoping Traits

> ⚠️ **STATUS UPDATE (2026-01-30)**: Checklist below was INCORRECT. Items marked "Done" in SSO Package do NOT exist. They need to be implemented.

## Tổng quan

SSO Package sẽ cung cấp các Traits giúp Models tự động filter data theo context (Organization, Branch, Team).

---

## Implementation Checklist

### SSO Package (Shared) - ⚠️ CẦN TRIỂN KHAI

| Component | Status | Target Location |
|-----------|--------|-----------------|
| `HasOrganizationScope` trait | ⬜ TODO | `src/Traits/HasOrganizationScope.php` |
| `HasBranchScope` trait | ⬜ TODO | `src/Traits/HasBranchScope.php` |
| `HasTeamScope` trait | ⬜ TODO | `src/Traits/HasTeamScope.php` |
| `Context` facade | ⬜ TODO | `src/Facades/Context.php` |
| `ContextService` | ⬜ TODO | `src/Services/ContextService.php` |
| `sso.require-organization` middleware | ⬜ TODO | `src/Http/Middleware/RequireOrganization.php` |
| `sso.require-branch` middleware | ⬜ TODO | `src/Http/Middleware/RequireBranch.php` |
| `sso.with-branch` middleware | ⬜ TODO | `src/Http/Middleware/WithBranch.php` |

### Timesheet Service (Main App) - ✅ ĐÃ CÓ (tạm thời)

| Component | Status | Location |
|-----------|--------|----------|
| `HasOrganizationScope` trait | ✅ Done | `app/Traits/HasOrganizationScope.php` |
| `HasEmployeeScope` trait | ✅ Done | `app/Traits/HasEmployeeScope.php` |
| Apply to TimeLog | ✅ Done | `app/Models/TimeLog.php` |
| Context headers in API routes | ✅ Done | `routes/api.php` (via `sso.organization`) |

### React SSO Package (@famgia/omnify-react-sso)

| Component | Status | Location |
|-----------|--------|----------|
| `useOrganization` hook | ✅ Done | `src/core/hooks/useOrganization.ts` |
| `useBranch` hook | ✅ Done | `src/core/hooks/useBranch.ts` |
| `SsoProvider` context | ✅ Done | `src/core/context/SsoProvider.tsx` |
| `BranchProvider` context | ✅ Done | `src/core/context/BranchProvider.tsx` |
| `orgSync` util | ✅ Done | `src/core/utils/orgSync.ts` |
| `branchHeaders` util | 🔄 Partial | `src/core/utils/branchHeaders.ts` |
| Branch permission checks | ⬜ TODO | - |

### Database

| Component | Status | Notes |
|-----------|--------|-------|
| `users.current_org_id` | 🔄 Session | Tracked via session |
| `users.current_branch_id` | 🔄 Session | Tracked via session |
| `role_user.branch_id` | ⬜ TODO | Branch-specific roles |

**Legend:** ✅ Done | 🔄 In Progress | ⬜ TODO

---

## Migration Plan

### Phase 1: Move traits to SSO Package
```
Current: app/Traits/HasOrganizationScope.php (Main App)
Target:  packages/.../src/Traits/HasOrganizationScope.php (SSO Package)
```

### Phase 2: Create new components in SSO Package
- ContextService & Context facade
- Middleware (require-organization, require-branch, with-branch)

### Phase 3: Update Main App
- Change `use App\Traits\HasOrganizationScope` to `use Omnify\SsoClient\Traits\HasOrganizationScope`

---

## ⚠️ Lưu ý về BaseModel (Omnify)

Omnify đã generate sẵn trong BaseModel:
- Relationship: `organization()`, `branch()`, `team()`
- Foreign key: `organization_id`, `branch_id`, `team_id`

**Traits này CHỈ thêm query scopes**, không override relationships.

---

## Traits có sẵn trong SSO Package

| Trait | Dùng cho | Scopes được thêm |
|-------|----------|------------------|
| `HasOrganizationScope` | Models có `organization_id` | `forOrganization()`, `inCurrentOrganization()` |
| `HasBranchScope` | Models có `branch_id` | `forBranch()`, `inCurrentBranch()`, `inCurrentContext()` |
| `HasTeamScope` | Models có `team_id` | `forTeam()`, `inCurrentTeam()` |

**Không conflict với BaseModel vì:**
- BaseModel có: `organization()` → relationship method
- Trait thêm: `inCurrentOrganization()` → query scope

---

## Cách sử dụng cơ bản

### 1. Model có organization_id

```php
use Omnify\SsoClient\Traits\HasOrganizationScope;

class Department extends DepartmentBaseModel
{
    use HasOrganizationScope;
    
    // BaseModel đã có: organization() relationship
    // Trait thêm: inCurrentOrganization() scope
}
```

**Query:**
```php
Department::forOrganization($organizationId)->get();
Department::inCurrentOrganization()->get();
```

### 2. Model có branch_id

```php
use Omnify\SsoClient\Traits\HasBranchScope;

class Device extends DeviceBaseModel
{
    use HasBranchScope;
    
    // BaseModel đã có: organization(), branch() relationships
    // Trait thêm: inCurrentBranch(), inCurrentContext() scopes
}
```

**Query:**
```php
Device::forBranch($branchId)->get();
Device::inCurrentBranch()->get();
Device::inCurrentContext()->get();
```

### 3. Model có team_id

```php
use Omnify\SsoClient\Traits\HasTeamScope;

class TaskAssignment extends TaskAssignmentBaseModel
{
    use HasTeamScope;
    
    // BaseModel đã có: organization(), team() relationships
    // Trait thêm: inCurrentTeam() scope
}
```

**Query:**
```php
TaskAssignment::forTeam($teamId)->get();
TaskAssignment::inCurrentTeam()->get();
```

---

## Lưu ý quan trọng: User vs Employee

```
┌─────────────────────────────────────────────────────────────────┐
│  Console (SSO) quản lý:                                          │
│  ✓ User - Account đăng nhập hệ thống                            │
│  ✓ Organization, Branch, Team - Cấu trúc tổ chức                │
│                                                                  │
│  Mỗi Service tự quản lý:                                         │
│  ✓ Employee - Nhân viên trong nghiệp vụ của service             │
└─────────────────────────────────────────────────────────────────┘
```

**Không phải Employee nào cũng có User account:**
- Part-time: không có account, manager nhập thay
- Contract worker: không có account
- Retired: account bị deactivate nhưng data Employee vẫn còn

→ Mỗi service tự tạo `HasEmployeeScope` trait riêng.

---

## Case Studies

---

### Case 1: List departments (Org-scoped, đơn giản)

**Scenario:** Admin muốn xem danh sách departments trong organization.

**API:** `GET /api/departments`  
**Header:** `X-Organization-Id: 1`

**Model:** `Department` đã có `HasOrganizationScope` trait.

**Controller:**
```php
public function index()
{
    return Department::inCurrentOrganization()->get();
}
```

**Kết quả:**
- User thuộc Org 1 → Thấy departments của Org 1
- User thuộc Org 2 → Thấy departments của Org 2

---

### Case 2: List devices (Branch-scoped, có/không có branch context)

**Scenario:** Xem devices. Nếu chọn branch thì filter theo branch, không thì hiện tất cả trong org.

**API:** `GET /api/devices`  
**Header:** `X-Organization-Id: 1`, `X-Branch-Id: 5` (optional)

**Model:** `Device` đã có `HasBranchScope` trait.

**Controller:**
```php
public function index()
{
    $query = Device::inCurrentOrganization();
    
    if (Context::hasBranch()) {
        $query->inCurrentBranch();
    }
    
    return $query->get();
}
```

**Kết quả:**
- Có `X-Branch-Id: 5` → Chỉ devices của Branch 5
- Không có `X-Branch-Id` → Tất cả devices trong Org 1

---

### Case 3: List locations (Branch-scoped, bắt buộc branch)

**Scenario:** Locations phải gắn với branch cụ thể, không cho phép xem tất cả.

**API:** `GET /api/locations`  
**Header:** `X-Organization-Id: 1`, `X-Branch-Id: 5` (required)

**Route:**
```php
Route::get('/locations', [LocationController::class, 'index'])
    ->middleware('sso.require-branch');
```

**Controller:**
```php
public function index()
{
    return Location::inCurrentContext()->get();
}
```

**Kết quả:**
- Có `X-Branch-Id` → Locations của branch đó
- Không có `X-Branch-Id` → 400 Bad Request

---

### Case 4: User thuộc nhiều Organizations

**Scenario:** Tanaka làm việc cho 2 công ty:
- Company A (organization_id: 1): Manager
- Company B (organization_id: 2): Staff

**Database:**
```
users: { id: 10, name: "Tanaka", current_organization_id: 1 }
role_user: 
  - { user_id: 10, role_id: 2, organization_id: 1 }  // Manager ở Company A
  - { user_id: 10, role_id: 3, organization_id: 2 }  // Staff ở Company B
```

**Khi Tanaka đang ở Company A (current_organization_id: 1):**
```php
Department::inCurrentOrganization()->get();
// → Departments của Company A
// → Tanaka có quyền Manager
```

**Khi Tanaka switch sang Company B:**
```php
// API: POST /api/context/switch
// Body: { "organization_id": 2 }

Department::inCurrentOrganization()->get();
// → Departments của Company B
// → Tanaka có quyền Staff (hạn chế hơn)
```

---

### Case 5: User thuộc nhiều Branches với roles khác nhau

**Scenario:** Suzuki làm việc tại 2 chi nhánh:
- Tokyo Branch (branch_id: 1): Team Lead
- Osaka Branch (branch_id: 2): Staff

**Database:**
```
role_user:
  - { user_id: 20, role_id: 4, organization_id: 1, branch_id: 1 }  // Team Lead @ Tokyo
  - { user_id: 20, role_id: 3, organization_id: 1, branch_id: 2 }  // Staff @ Osaka
```

**Khi Suzuki ở Tokyo Branch:**
```php
// current_branch_id: 1
Timesheet::visibleToCurrentUser()->get();
// → Thấy timesheets của cả team (Team Lead role)
```

**Khi Suzuki switch sang Osaka Branch:**
```php
// current_branch_id: 2
Timesheet::visibleToCurrentUser()->get();
// → Chỉ thấy timesheet của bản thân (Staff role)
```

---

### Case 6: Employee không có account (Part-time)

**Scenario:** Yamada là part-time, không có account. Manager Tanaka nhập timesheet cho Yamada.

**Database:**
```
employees:
  - { id: 100, user_id: NULL, name: "Yamada", organization_id: 1, branch_id: 1 }
  - { id: 101, user_id: 10, name: "Tanaka", organization_id: 1, branch_id: 1 }
```

**Tanaka tạo timesheet cho Yamada:**
```php
// API: POST /api/timesheets
// Body: { "employee_id": 100, "date": "2026-01-30", ... }

// Controller kiểm tra quyền:
public function store(Request $request)
{
    $employee = Employee::find($request->employee_id);
    
    // Tanaka là Manager, có quyền tạo cho nhân viên trong team
    $this->authorize('createFor', $employee);
    
    return Timesheet::create([...]);
}
```

---

### Case 7: Hierarchical data visibility (Position-based)

**Scenario:** Xem timesheets dựa theo cấp bậc.

**Visibility matrix:**

| Position | Thấy được |
|----------|-----------|
| Staff | Chỉ timesheet của mình |
| Team Lead | Timesheets của team mình |
| Manager | Timesheets của department mình |
| Director | Tất cả trong branch |
| Admin | Tất cả trong organization |

**Lưu ý:** `HasEmployeeScope` là trait của **SERVICE**, không phải SSO Package.

**Sử dụng:**
```php
Timesheet::visibleToCurrentUser()->get();
```

---

### Case 8: Cross-branch data access

**Scenario:** HR Manager cần xem data của tất cả branches để làm báo cáo.

**Database:**
```
role_user:
  - { user_id: 30, role_id: 5, organization_id: 1, branch_id: NULL }  // HR Manager, không giới hạn branch
```

**Controller:**
```php
public function report()
{
    // HR Manager không có branch_id trong role → có thể xem tất cả
    if (Context::hasOrgWideAccess()) {
        return Timesheet::inCurrentOrganization()->get();
    }
    
    // User bình thường → chỉ xem branch mình
    return Timesheet::inCurrentBranch()->get();
}
```

---

### Case 9: Filter kết hợp nhiều điều kiện

**Scenario:** List timesheets với filters: branch, department, date range, status.

**API:** `GET /api/timesheets?branch_id=1&department_id=5&from=2026-01-01&to=2026-01-31&status=pending`

**Controller:**
```php
public function index(Request $request)
{
    $query = Timesheet::visibleToCurrentUser();
    
    // Filter theo branch (nếu có quyền)
    if ($request->branch_id && Context::canAccessBranch($request->branch_id)) {
        $query->where('branch_id', $request->branch_id);
    }
    
    // Filter theo department
    if ($request->department_id) {
        $query->whereHas('employee', fn($q) => 
            $q->where('department_id', $request->department_id)
        );
    }
    
    // Filter theo date range
    if ($request->from && $request->to) {
        $query->whereBetween('date', [$request->from, $request->to]);
    }
    
    // Filter theo status
    if ($request->status) {
        $query->where('status', $request->status);
    }
    
    return $query->paginate();
}
```

---

### Case 10: Tạo record mới với auto-fill context

**Scenario:** Tạo timesheet mới, tự động gán org_id và branch_id từ context.

**Behavior:** Traits tự động fill `organization_id`, `branch_id` khi tạo record mới.

**Controller:**
```php
public function store(Request $request)
{
    // Không cần truyền org_id, branch_id - tự động fill từ context
    return Timesheet::create([
        'employee_id' => $request->employee_id,
        'date' => $request->date,
    ]);
}
```

**Kết quả:** Record được tạo với org/branch từ user context hiện tại.

---

### Case 11: Prevent cross-org data access

**Scenario:** User cố tình truyền org_id khác trong request body.

**Request:** 
```json
{
  "organization_id": 999,  // Cố tình truyền org khác
  "name": "Hack Department"
}
```

**Behavior:** Traits tự động:
1. **Creating:** Luôn dùng context, ignore org_id từ request
2. **Updating:** Không cho phép đổi organization_id

**Kết quả:**
- Request với `organization_id: 999` → Record vẫn được tạo với org_id từ context (VD: 1)
- Cố đổi org_id khi update → `ForbiddenException`

---

### Case 12: Soft delete với scope

**Scenario:** Xem cả records đã xóa (cho admin).

**Query bình thường:**
```php
Department::inCurrentOrganization()->get();
// → Không bao gồm soft deleted
```

**Query bao gồm deleted (admin):**
```php
Department::inCurrentOrganization()->withTrashed()->get();
// → Bao gồm cả đã xóa
```

---

### Case 13: Eager loading với scope

**Scenario:** Load timesheets kèm employee, đảm bảo employee cũng trong cùng context.

**Query:**
```php
Timesheet::inCurrentContext()
    ->with(['employee' => fn($q) => $q->inCurrentOrganization()])
    ->get();
```

**Hoặc dùng constrained relationship trong Model:**
```php
class Timesheet extends Model
{
    public function employee()
    {
        return $this->belongsTo(Employee::class)
            ->where('organization_id', $this->organization_id);
    }
}
```

---

### Case 14: Validation với scope

**Scenario:** Validate employee_id phải thuộc cùng organization.

**Form Request:**
```php
public function rules()
{
    return [
        'employee_id' => [
            'required',
            Rule::exists('employees', 'id')
                ->where('organization_id', Context::organizationId()),
        ],
    ];
}
```

---

### Case 15: Report aggregation với scope

**Scenario:** Tổng hợp giờ làm theo department trong org.

**Query:**
```php
$report = Timesheet::inCurrentOrganization()
    ->selectRaw('department_id, SUM(total_hours) as hours')
    ->join('employees', 'timesheets.employee_id', '=', 'employees.id')
    ->groupBy('department_id')
    ->get();
```

---

## Mapping: Model → Trait

| Model | Trait | Package | Scope Level |
|-------|-------|---------|-------------|
| Department | `HasOrganizationScope` | SSO | Org |
| Position | `HasOrganizationScope` | SSO | Org |
| WorkType | `HasOrganizationScope` | SSO | Org |
| TimePolicy | `HasOrganizationScope` | SSO | Org |
| Device | `HasBranchScope` | SSO | Branch |
| Location | `HasBranchScope` | SSO | Branch |
| Shift | `HasBranchScope` | SSO | Branch |
| Team | `HasBranchScope` | SSO | Branch |
| **Employee** | `HasEmployeeScope` | **Service** | Employee |
| **Timesheet** | `HasEmployeeScope` | **Service** | Employee |
| **TimeEntry** | `HasEmployeeScope` | **Service** | Employee |
| **Attendance** | `HasEmployeeScope` | **Service** | Employee |
| **LeaveRequest** | `HasEmployeeScope` | **Service** | Employee |

**Lưu ý:** BaseModel (Omnify) đã có relationships (`organization()`, `branch()`). Traits chỉ thêm query scopes.

---

## Tài liệu liên quan

- [API Scoping Design](./api-scoping-design.md) - Middleware cho API routes
- [Database Design](./database-design-simplified.md) - Cấu trúc database
- [Ecosystem Architecture](./ecosystem-architecture.md) - Kiến trúc tổng thể
