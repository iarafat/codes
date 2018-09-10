<?php

namespace App;

use Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\SoftDeletes, Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Program extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $guarded = [];
    private $lastMachineId;

    public static function boot()
    {
        parent::boot();

        Pivot::created(function ($pivot) {});
    }

    // store method
    public function store($request)
    {
        $machine = Machine::get();
        if ($machine == null) {
            return false;
        }

        DB::transaction(function () use ($request, &$result) {
            if (isset($request->id)) {
                $program = Program::find($request->id);
            } else {
                $program = new Program;
            }
            $request['delivery_date'] = date('Y-m-d H:i:s', strtotime($request->delivery_date));

            if (!isset($request->id)) {
                $storedProgram = $program->create($request->all());
                $result = $this->setProgramToMachine($storedProgram);
            } else {
                $program->update($request->all());
                $result = $this->updateProgramToMachine($program);
            }
        });
        return $result;
    }

    private function setProgramToMachine($program)
    {
        $machines = $this->allMachines();
        $programEndDateTime = [];
        $expectedMachinesId = [];
        $suitableWeight = $this->findSuitableWeight($machines, $program);

        foreach ($machines as $key => $machine) {
            if (($program->weight < $machine->usable_capacity) && ($machine->usable_capacity <= $suitableWeight)) {
                $lastProgram = $this->lastMachineProgram($machine);

                if (is_null($lastProgram)) {
                    $sequenceId = 1;
                    $startDateTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d') . "+6 hours"));
                    $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
                } else {
                    $sequenceId = $this->sumSequenceId($lastProgram);
                    $startDateTime = $this->expectedEndDatetime($lastProgram);
                    $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
                }

                if (is_null($lastProgram)) {
                    $this->attachProgramAndMachine($program, $machine->id, $startDateTime, $endDateTime, $sequenceId, 0);
                    $loopOutput = true;
                    break;
                } else {
                    $programEndDateTime[] = $this->expectedEndDatetime($lastProgram);
                    $expectedMachinesId[] = $machine->id;
                    if ($program->delivery_date > $this->expectedEndDatetime($lastProgram)) {
                        $this->attachProgramAndMachine($program, $machine->id, $startDateTime, $endDateTime, $sequenceId,0);
                        $loopOutput = true;
                        break;
                    } else {
                        $loopOutput = false;
                        continue;
                    }
                }
            }
        }

        if (!$loopOutput) {
            $oneLowestEndDateTime = min($programEndDateTime);
            for ($i = 0; $i < count($programEndDateTime); $i++){
                if ($oneLowestEndDateTime == $programEndDateTime[$i]){
                    $this->lastMachineId = $expectedMachinesId[$i];
                }
                $machineIdAndProgramEndTime[] = [$expectedMachinesId[$i], $programEndDateTime[$i]];
            }

            $availableMachine = $this->findLowestMachine($this->lastMachineId);
            $lowestMachine = $this->lowestMachine($availableMachine, $oneLowestEndDateTime);
            $sequenceId = $this->sumSequenceId($lowestMachine);
            $startDateTime = $this->expectedEndDatetime($lowestMachine);
            $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
            $this->attachProgramAndMachine($program, $availableMachine->id, $startDateTime, $endDateTime, $sequenceId,1);
            $loopOutput = true;
        }
        return $loopOutput;
    }

    private function updateProgramToMachine($program)
    {
        $machines = $this->allMachines();
        $programEndDateTime = [];
        $expectedMachinesId = [];
        $suitableWeight = $this->findSuitableWeight($machines, $program);

        foreach ($machines as $key => $machine) {
            if (($program->weight < $machine->usable_capacity) && ($machine->usable_capacity <= $suitableWeight)) {
                $lastProgram = $this->lastMachineProgram($machine);

                if (is_null($lastProgram)) {
                    $sequenceId = 1;
                    $startDateTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d') . "+6 hours"));
                    $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
                } else {
                    $sequenceId = $this->sumSequenceId($lastProgram);
                    $startDateTime = $this->expectedEndDatetime($lastProgram);
                    $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
                }

                if (is_null($lastProgram)) {
                    $this->updateAttachProgramAndMachine($program, $machine->id, $startDateTime, $endDateTime, $sequenceId, 0);
                    $loopOutput = true;
                    break;
                } else {
                    $programEndDateTime[] = $this->expectedEndDatetime($lastProgram);
                    $expectedMachinesId[] = $machine->id;
                    if ($program->delivery_date > $this->expectedEndDatetime($lastProgram)) {
                        $this->updateAttachProgramAndMachine($program, $machine->id, $startDateTime, $endDateTime, $sequenceId,0);
                        $loopOutput = true;
                        break;
                    } else {
                        $loopOutput = false;
                        continue;
                    }
                }
            }
        }

        if (!$loopOutput) {
            $oneLowestEndDateTime = min($programEndDateTime);
            for ($i = 0; $i < count($programEndDateTime); $i++){
                if ($oneLowestEndDateTime == $programEndDateTime[$i]){
                    $this->lastMachineId = $expectedMachinesId[$i];
                }
                $machineIdAndProgramEndTime[] = [$expectedMachinesId[$i], $programEndDateTime[$i]];
            }

            $availableMachine = $this->findLowestMachine($this->lastMachineId);
            $lowestMachine = $this->lowestMachine($availableMachine, $oneLowestEndDateTime);
            $sequenceId = $this->sumSequenceId($lowestMachine);
            $startDateTime = $this->expectedEndDatetime($lowestMachine);
            $endDateTime = $this->dyeingEndDatetime($startDateTime, $program->dyeing_hour);
            $this->updateAttachProgramAndMachine($program, $availableMachine->id, $startDateTime, $endDateTime, $sequenceId,1);
            $loopOutput = true;
        }
        return $loopOutput;
    }

    public function updateSequenceId($request)
    {
        $programs = $request->program_id;
        for ($i = 0; $i < count($request->program_id); $i++){
            $this->updateSingleSequenceId($request->machine_id, $programs[$i], $i+1);
        }

    }

    private function updateSingleSequenceId($machineId, $programId, $sequenceId)
    {
        $update = Program::find($programId)->machines()->wherePivot('program_id', $programId)->first();
        $update->pivot->machine_id = $machineId;
        $update->pivot->sequence_id = $sequenceId;
        return $update->pivot->save();
    }

    private function allMachines()
    {
        return Machine::with('programs')
            ->orderBy('capacity', 'asc')
            ->get();
    }

    private function findLowestMachine($machineId)
    {
        return Machine::find($machineId);
    }

    private function findSuitableWeight($machines, $program)
    {
        foreach ($machines as $key => $machine) {
            if ($program->weight < $machine->usable_capacity) {
                return $machine->usable_capacity;
            }
        }
    }

    private function lastMachineProgram($machine)
    {
        return $machine->programs()->orderBy('machine_program.sequence_id', 'desc')->first();
    }

    private function lowestMachine($machine, $lowestTime)
    {
        return $machine->programs()->where('machine_program.expected_end_datatime', $lowestTime)->first();
    }

    private function dyeingEndDatetime($time, $hours)
    {
        return date('Y-m-d H:i:s', strtotime($time . "+$hours" . 'hours'));
    }

    private function expectedEndDatetime($lastProgram)
    {
        return $lastProgram->pivot->expected_end_datatime;
    }

    private function sumSequenceId($lastProgram)
    {
        return $lastProgram->pivot->sequence_id + 1;
    }

    private function attachProgramAndMachine($program, $machineId, $startDateTime, $endDateTime, $sequenceId, $needFirst)
    {
        $program->machines()->attach($machineId, [
            'expected_start_datatime' => $startDateTime,
            'expected_end_datatime' => $endDateTime,
            'sequence_id' => $sequenceId,
            'is_need_first' => $needFirst,
        ]);
    }

    private function updateAttachProgramAndMachine($program, $machineId, $startDateTime, $endDateTime, $sequenceId, $needFirst)
    {
        $update = $program->machines()->wherePivot('program_id', $program->id)->first();
        $update->pivot->machine_id = $machineId;
        $update->pivot->expected_start_datatime = $startDateTime;
        $update->pivot->expected_end_datatime = $endDateTime;
        $update->pivot->sequence_id = $sequenceId;
        $update->pivot->is_need_first = $needFirst;
        return $update->pivot->save();
    }

    public function machines()
    {
        return $this->belongsToMany(Machine::class)->withPivot(
            'expected_start_datatime',
            'expected_end_datatime',
            'actual_start_datatime',
            'actual_end_datatime',
            'sequence_id',
            'is_need_first'
        );
    }
}
