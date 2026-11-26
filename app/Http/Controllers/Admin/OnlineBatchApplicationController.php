<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\OnlineBatchApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OnlineBatchApplicationController extends Controller {
 public function index(Request $request) {
  $applications=OnlineBatchApplication::with(['student','batch.course','reviewer'])->when($request->filled('status'),fn($q)=>$q->where('status',$request->input('status')))->latest()->paginate(25)->withQueryString();
  return view('admin.online-batch-applications.index',compact('applications'));
 }
 public function approve(Request $request,OnlineBatchApplication $application) {
  $data=$request->validate(['review_note'=>['nullable','string','max:2000']]);
  DB::transaction(function()use($application,$data){
   $application=OnlineBatchApplication::lockForUpdate()->findOrFail($application->id);
   abort_unless($application->status==='pending',422,'এই আবেদনটি আর pending নেই।');
   $batch=$application->batch()->lockForUpdate()->firstOrFail();
   abort_unless(in_array($batch->status,['upcoming','ongoing'],true),422,'এই অনলাইন ব্যাচে ভর্তি বন্ধ।');
   abort_if($batch->capacity&&$batch->activeStudentCount()>=$batch->capacity,422,'এই অনলাইন ব্যাচের আসন পূর্ণ।');
   $enrollment=Enrollment::where('user_id',$application->student_id)->where('course_id',$application->course_id)->lockForUpdate()->first();
   if($enrollment&&$enrollment->batch_id&&(int)$enrollment->batch_id!==(int)$batch->id) abort(422,'শিক্ষার্থী এই কোর্সের অন্য অনলাইন ব্যাচে ইতিমধ্যে যুক্ত আছেন।');
   if($enrollment) $enrollment->update(['batch_id'=>$batch->id,'status'=>'active']);
   else Enrollment::create(['user_id'=>$application->student_id,'course_id'=>$application->course_id,'batch_id'=>$batch->id,'order_id'=>null,'price_paid'=>0,'status'=>'active','enrolled_at'=>now()]);
   $application->update(['status'=>'approved','review_note'=>$data['review_note']??null,'reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'approved_at'=>now()]);
  });
  return back()->with('success','অনলাইন ব্যাচের আবেদন অনুমোদিত হয়েছে।');
 }
 public function reject(Request $request,OnlineBatchApplication $application) {
  $data=$request->validate(['review_note'=>['required','string','max:2000']]);
  abort_unless($application->status==='pending',422);
  $application->update(['status'=>'rejected','review_note'=>$data['review_note'],'reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
  return back()->with('success','অনলাইন ব্যাচের আবেদন প্রত্যাখ্যান করা হয়েছে।');
 }
}
