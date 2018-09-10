<?php

namespace App\Http\Controllers;

use App\Decision;
use App\FollowUp;
use App\Invitation;
use App\Mail\MeetingNotify;
use App\Meeting;
use App\Personnel;
use App\Project;
use App\Tag;
use App\Agenda;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MeetingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $meetings = Meeting::MeetingWithRelations();
        return view('meetings.index', compact('meetings'));
    }

    public function create()
    {
        $projects = Project::ownerProjects();
        $tags = Project::AllTag();
        return view('meetings.create', compact('projects', 'tags'));
    }

    public function getLocations(Request $request)
    {
        $locations = Project::find($request->project_id)->locations;
        $members = Project::find($request->project_id)->members;
        $tags = Project::find($request->project_id)->tags;
        $setData = [];

        foreach ($locations as $location) {
            $setData['locations'][] = '<option value="'.$location->id.'">'.$location->details.'</option>';
        }
        foreach ($tags as $key => $tag) {
            $setData['tags'][] = '<option value="'.$tag->id.'">'.$tag->name.'</option>';
        }
        foreach ($members as $key => $member) {
            $setData['members'][] = '<option value="'.$member->id.'">'.$member->name.'</option>';
        }

        return response()->json($setData);
    }

    public function store(Request $request, Meeting $meeting)
    {
        $request->validate([
            'project_id' => 'required',
            'subject' => 'required|max:255',
            'location_id' => 'required',
            'start' => 'required',
            'end' => 'required',
            'tags' => 'required',
        ]);

        try {
            $storeMeeting = $meeting->create($request->all());
            $storeMeeting->tags()->attach($request->tags);

            foreach ($request->attendees as $key => $attendee) {
                Personnel::create([
                    'personneltable_id' => $storeMeeting->id,
                    'personneltable_type' => 'meetings',
                    'user_id' => $attendee,
                    'user_type' => 'attendees',
                ]);
            }

            foreach ($request->callers as $key => $caller) {
                Personnel::create([
                    'personneltable_id' => $storeMeeting->id,
                    'personneltable_type' => 'meetings',
                    'user_id' => $caller,
                    'user_type' => 'callers',
                ]);
            }

            $storeMeeting->agendas()->createMany($request->agendas);
            $storeMeeting->followUps()->createMany($request->follow_ups);

            //$this->sendMeetingNotification($storeMeeting);
            return redirect()->route('meetings.index')->with('status', 'Stored successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        $meeting = Meeting::MeetingWithAllRelations($id);
        //dd($meeting);
        $projects = Project::ownerProjects();
        return view('meetings.edit', compact('meeting', 'projects'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'project_id' => 'required',
            'subject' => 'required|max:255',
            'location_id' => 'required',
            'start' => 'required',
            'end' => 'required',
            'tags' => 'required',
        ]);
        try {
            $meeting = Meeting::find($id);
            $meeting->update($request->all());
            $meeting->tags()->sync($request->tags);

            Personnel::where(['personneltable_id' => $id,'personneltable_type' => 'meetings', 'user_type' => 'attendees'])->whereIn('user_id', $request->attendees)->delete();
            foreach ($request->attendees as $key => $attendee) {
                Personnel::create([
                    'personneltable_id' => $id,
                    'personneltable_type' => 'meetings',
                    'user_id' => $attendee,
                    'user_type' => 'attendees',
                ]);
            }

            Personnel::where(['personneltable_id' => $id,'personneltable_type' => 'meetings', 'user_type' => 'callers'])->whereIn('user_id', $request->callers)->delete();
            foreach ($request->callers as $key => $caller) {
                Personnel::create([
                    'personneltable_id' => $id,
                    'personneltable_type' => 'meetings',
                    'user_id' => $caller,
                    'user_type' => 'callers',
                ]);
            }

            foreach ($request->agendas as $key => $agenda){
                if (is_null($agenda['id'])){
                    $meeting->agendas()->create(['details' => $agenda['details']]);
                } else {
                    $meeting->agendas()->where('id', $agenda['id'])->update([
                        'details' => $agenda['details']
                    ]);
                }
            }
            foreach ($request->follow_ups as $key => $follow_up){
                if (is_null($follow_up['id'])){
                    $meeting->followUps()->create(['details' => $follow_up['details']]);
                } else {
                    $meeting->followUps()->where('id', $follow_up['id'])->update([
                        'details' => $follow_up['details']
                    ]);
                }
            }

            //$this->sendMeetingNotification($meeting);
            return back()->with('status', 'Stored successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }


    public function fromInvitation(Invitation $invitation)
    {
        $invitationRelations = Invitation::InvitationWithAllRelations($invitation->id);
        $projects = Project::ownerProjects();
        $projectTags = optional(Project::ProjectWithTags($invitation->project_id))->tags;
        return view('meetings.from-invitation', compact('projects', 'invitationRelations', 'projectTags'));
    }
    
    
    private function sendMeetingNotification($meeting) {
        $attendeesEmails = $meeting->attendees()->select('email')->get()->toArray();
        $callersEmails = $meeting->callers()->select('email')->get()->toArray();
        $emails = array_merge($attendeesEmails,$callersEmails);
        $meeting = Meeting::MeetingWithProject($meeting->id);
        $meetingCreator = auth()->user()->name;
        foreach ($emails as $email) {
            Mail::to($email['email'])->send(new MeetingNotify($meeting, $meetingCreator));
        }
    }

    public function deleteAgenda(Request $request)
    {
        $agenda = Agenda::find($request->id);
        $agenda->delete();
    }

    public function deleteFollowUp(Request $request)
    {
        $followUp = FollowUp::find($request->id);
        $followUp->delete();
    }

    public function delete(Meeting $meeting)
    {
        if ($meeting->delete()) {
            return back()->with('status', 'Deleted successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function trashIndex()
    {
        $trashMeetings = Meeting::TrashMeetingWithRelations();
        return view('meetings.index', compact('trashMeetings'));
    }

    public function restore($id)
    {
        if (Meeting::onlyTrashed()->find($id)->restore()) {
            return back()->with('status', 'Restored successfully');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function deletePermanently($id)
    {
        if (Meeting::onlyTrashed()->find($id)->forceDelete()) {
            return back()->with('status', 'Permanently Deleted');
        } else {
            return back()->withErrors([
                'error' => 'Something want wrong! Please try again'
            ]);
        }
    }

    public function addDecision(Meeting $meeting)
    {
        $currentTime = Carbon::now();
        if ($meeting->end < $currentTime) {
            $decisions = $meeting->decisions()->get();
            return view('meetings.decision', compact('meeting', 'decisions'));
        } else {
            return back()->withErrors([
                'error' => 'Meeting not finish yet'
            ]);
        }

    }

    public function storeDecision(Meeting $meeting, Request $request)
    {
        try {
            if ($request->dataset == 'no') {
                $meeting->decisions()->createMany($request->decisions);
            } else {
                $this->updateDecision($meeting, $request);
            }

            return back()->with('status', 'Stored successfully');
        } catch (\Exception $exception) {
            return redirect()->back()
                ->withErrors($exception->getMessage())
                ->withInput();
        }
    }

    private function updateDecision($meeting, $request) {
        foreach ($request->decisions as $key => $decision){
            if (is_null($decision['id'])){
                $meeting->decisions()->create(['details' => $decision['details']]);
            } else {
                $meeting->decisions()->where('id', $decision['id'])->update([
                    'details' => $decision['details']
                ]);
            }
        }
    }

    public function deletedDecision(Request $request)
    {
        $decision = Decision::find($request->id);
        $decision->delete();
    }
}
