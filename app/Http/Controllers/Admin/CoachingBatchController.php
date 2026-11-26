<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CoachingBatch;
use App\Models\Course;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class CoachingBatchController extends Controller {
 public function index() {
  $batches=CoachingBatch::with(['branch','course','instructor'])->withCount(['enrollments as active_students'=>fn($q)=>$q->where('status','active')])->orderBy('branch_id')->orderBy('name')->paginate(25);
  return view('admin.coaching-batches.index',compact('batches'));
 }
 public function create() { return view('admin.coaching-batches.form',$this->formData(new CoachingBatch(['status'=>'upcoming']))); }
 public function store(Request $request) { CoachingBatch::create($this->validated($request)); return redirect()->route('admin.coaching-batches.index')->with('success','সরাসরি কোচিং ব্যাচ তৈরি হয়েছে।'); }
 public function edit(CoachingBatch $coachingBatch) { return view('admin.coaching-batches.form',$this->formData($coachingBatch)); }
 public function update(Request $request,CoachingBatch $coachingBatch) { $coachingBatch->update($this->validated($request,$coachingBatch)); return redirect()->route('admin.coaching-batches.index')->with('success','কোচিং ব্যাচ আপডেট হয়েছে।'); }
 public function destroy(CoachingBatch $coachingBatch) {
  abort_if($coachingBatch->enrollments()->exists()||$coachingBatch->admissions()->whereIn('status',['pending','awaiting_payment','admitted'])->exists(),422,'এই ব্যাচে আবেদন বা শিক্ষার্থী আছে; আগে তাদের স্থানান্তর/সমাধান করুন।');
  $coachingBatch->delete(); return back()->with('success','কোচিং ব্যাচ মুছে ফেলা হয়েছে।');
 }
 private function formData(CoachingBatch $batch): array {
  return ['coachingBatch'=>$batch,'branches'=>Branch::active()->orderBy('name')->get(),'courses'=>Course::orderBy('title')->get(),'levels'=>Level::orderBy('position')->get(),'instructors'=>User::where('role','instructor')->where('is_active',true)->orderBy('name')->get()];
 }
 private function validated(Request $request,?CoachingBatch $batch=null): array {
  return $request->validate([
   'branch_id'=>['required','exists:branches,id'],'course_id'=>['required','exists:courses,id'],
   'level_id'=>['nullable','exists:levels,id'],'instructor_id'=>['nullable','exists:users,id'],
   'name'=>['required','string','max:120'],'code'=>['required','string','max:30',Rule::unique('coaching_batches','code')->ignore($batch?->id)],
   'description'=>['nullable','string','max:2000'],'admission_fee'=>['required','numeric','min:0','max:99999999.99'],
   'monthly_fee'=>['required','numeric','min:0','max:99999999.99'],'starts_at'=>['nullable','date'],
   'ends_at'=>['nullable','date','after_or_equal:starts_at'],'schedule_days'=>['nullable','string','max:120'],
   'schedule_time'=>['nullable','string','max:120'],'capacity'=>['nullable','integer','min:1'],'status'=>['required',Rule::in(['upcoming','ongoing','completed','cancelled'])],
  ]);
 }
}
