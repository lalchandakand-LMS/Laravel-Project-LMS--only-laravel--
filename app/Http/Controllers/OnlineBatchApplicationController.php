<?php
namespace App\Http\Controllers;
use App\Models\Batch;
use App\Models\OnlineBatchApplication;
use Illuminate\Http\Request;
class OnlineBatchApplicationController extends Controller {
 public function index() {
  abort_unless(auth()->user()->isStudent(),403);
  $applications=OnlineBatchApplication::with(['batch.course','reviewer'])->where('student_id',auth()->id())->latest()->get();
  $batches=Batch::with('course')->whereIn('status',['upcoming','ongoing'])->whereNotNull('course_id')->orderBy('starts_at')->get();
  return view('online-batches.applications',compact('applications','batches'));
 }
 public function store(Request $request) {
  abort_unless($request->user()->isStudent(),403);
  $data=$request->validate(['batch_id'=>['required','integer','exists:batches,id'],'student_note'=>['nullable','string','max:1000']]);
  $batch=Batch::with('course')->findOrFail($data['batch_id']);
  abort_unless(in_array($batch->status,['upcoming','ongoing'],true)&&$batch->course_id,422,'এই অনলাইন ব্যাচে আবেদন নেওয়া হচ্ছে না।');
  abort_if($batch->capacity&&$batch->activeStudentCount()>=$batch->capacity,422,'এই অনলাইন ব্যাচের আসন পূর্ণ।');
  $existing=OnlineBatchApplication::where('student_id',auth()->id())->where('batch_id',$batch->id)->first();
  if($existing&&in_array($existing->status,['pending','approved'],true)) return back()->withErrors(['batch_id'=>'এই ব্যাচে আপনার আবেদন বা ভর্তি ইতিমধ্যে রয়েছে।']);
  OnlineBatchApplication::updateOrCreate(['student_id'=>auth()->id(),'batch_id'=>$batch->id],['course_id'=>$batch->course_id,'status'=>'pending','student_note'=>$data['student_note']??null,'review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null,'approved_at'=>null]);
  return back()->with('success','অনলাইন লাইভ ক্লাসের ব্যাচে আবেদন জমা হয়েছে।');
 }
}
