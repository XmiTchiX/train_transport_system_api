<?php

namespace App\Http\Repositories;

use App\Models\Travel;
use App\Http\Controllers\NotificationsController as Notif;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

Class TravelRepository
{
    public function all()
    {
        
        return Travel::where('status', '!=','completed')->get();
    }

    public function createByRequest($request)
    {
        $response = [];
        $validator = Validator::make($request->all(), [
            'departure_station' => 'required|string',
            'departure_time' => 'required',
            'arrival_station' => 'required',
            'distance' => 'required',
            'estimated_duration' => 'required',
            'description' => 'required',
        ]);

        if($validator->fails()){
            $response["success"] = false;
            $response["errors"] = $validator->errors();
            return $response;
        }

        $travel = Travel::create([
            'departure_station' => $request->departure_station,
            'departure_time' => $request->departure_time,
            'arrival_station' => $request->arrival_station,
            'distance' => $request->distance,
            'estimated_duration' => $request->estimated_duration,
            'description' => $request->description,
            "status" => "pending"
        ]);

        $response["success"] = true;
        $response["data"] = $travel;
        return $response;
    }

    public function updateByRequest(Request $request, $travelId) {
        $response = [];
        $travel = Travel::find($travelId);
        if($travel) {
            $validator = Validator::make($request->all(), [
                'departure_station' => 'required|string',
                'departure_time' => 'required',
                'arrival_station' => 'required',
                'distance' => 'required',
                'status' => 'required',
                'estimated_duration' => 'required',
                'description' => 'required',
            ]);
            if($validator->fails()) {
                $response["success"] = false;
                $response["errors"] = $validator->errors();
                return $response;
            }else {
                $travel->departure_station = $request->departure_station;
                $travel->departure_time = $request->departure_time;
                $travel->arrival_station = $request->arrival_station;
                $travel->distance = $request->distance;
                $travel->estimated_duration = $request->estimated_duration;
                $travel->description = $request->description;
                if($request->status=='delayed'){
                    $travel->status = $request->status;
                    $message = Notif::sendMessage("Travel delayed to {$request->departure_time}",
                                                "the user was notified");
                } else if($request->status=='cancelled'){
                    $travel->status = $request->status;
                    foreach($travel->tickets as $ticket){
                        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                        $stripe->refunds->create([
                            'charge' => $ticket->payment_token,
                        ]);
                    }
                    $message = Notif::sendMessage("Travel cancelled, refunded",
                                                "the user was notified");
                }
                else $travel->status = $request->status;
                $travel->save();
                $response['notified'] = $message;
                $response["success"] = true;
                $response["data"] = $travel;
                return $response;
            }
        }else {
            $response["success"] = false;
            $response["errors"] = "Travel can not be found!";
            return $response;
        }
    }

    public function deleteById($travelId)
    {
        $response = [];
        $travel = Travel::find($travelId);
        if($travel) {
            $travel->delete();
            $response["success"] = true;
            $response["data"] = $travel;
            return $response;
        }else {
            $response["success"] = false;
            $response["errors"] = "Travel can not be found!";
            return $response;
        }
        return $travel;

    }


    public function travelsOfTheDay(){
        return [
            'data' => Travel::whereDate('departure_time', Carbon::today())->get(),
            'success' => true 
        ];
    }
}
