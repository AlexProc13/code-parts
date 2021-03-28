<?php

namespace App\Http\Controllers\API;

use DB;
use Hash;
use DateTime;
use Exception;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Modules\RawLog\RawLog;
use App\Models\PasswordQuestion;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\Traits\ApiAuthenticatesUsers;

class ResetQuestionController extends BaseController
{
    use ApiAuthenticatesUsers;

    public static $KEY = 'q_hash';

    public function get()
    {
        $questions = PasswordQuestion::select('id', 'question as text')->get()->sortBy('id')->toArray();

        $questions = array_values($questions);

        //translations
        $questionNames = [];
        foreach ($questions as $item) {
            $questionNames[] = 'extra' . tSign() . $item['text'];
        }

        $translations = transD($questionNames);
        foreach ($questions as $item) {
            $item['text'] = $translations['extra' . tSign() . $item['text']];
        }
        //translations

        return apiFormatResponse(true, '', $questions);
    }


    public function getUserQuestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ApiException('common.required_parameter_is_not_included');
        }

        $configUser = config('additional.users');
        $company = $request->currentCompany;
        $roles = $configUser['roles'];

        $user = User::where('company_id', $company->id)
            ->where('role_id', $roles['player'])
            ->where(function ($query) use ($request) {
                $query->where('username', $request->user_id)
                    ->orWhere('email', $request->user_id);
            })
            ->first();

        if (is_null($user)) {
            throw new ApiException('reset_password.some_wrong');
        }

        $questions = UserAnswer::select(['pq.id', 'pq.question as text'])
            ->join('password_questions as pq', 'pq.id', '=', 'user_answers.question_id')
            ->where('user_id', $user->id)
            ->orderBy('question_id')->get()->toArray();

        if (empty($questions)) {
            throw new ApiException('reset_password.some_wrong');
        }

        //translations
        $questionNames = [];
        foreach ($questions as $item) {
            $questionNames[] = 'extra' . tSign() . $item['text'];
        }

        $translations = transD($questionNames);
        foreach ($questions as $item) {
            $item['text'] = $translations['extra' . tSign() . $item['text']];
        }
        //translations

        return apiFormatResponse(true, '', $questions);
    }

    public function set(Request $request)
    {
        //VALIDATION
        $dataForReset = config('auth.resetPassword');
        $validator = Validator::make($request->all(), [
            'questions.*.id' => 'required|integer|exists:password_questions',
            'questions.*.answer' => 'required|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            throw new ApiException('common.required_parameter_is_not_included');
        }

        if (count($request->questions) > config('additional.resetQuestion.count')) {
            throw new ApiException('common.required_parameter_is_not_included');
        }

        $userId = auth()->user()->id;
        $needQuestions = UserAnswer::where('user_id', $userId)->get();

        if (empty($needQuestions)) {
            throw new ApiException('reset_password.questions_already_exist');
        }
        //VALIDATION

        //ACT
        //generate data hash
        $questions = collect($request->questions)->sortBy('id')->toArray();
        $hash = genHashQuestion($questions, $userId);

        $hashData = [
            'hash' => $hash,
            'attempts' => 1,
        ];

        DB::beginTransaction();
        foreach ($questions as $item) {
            UserAnswer::create([
                'user_id' => $userId,
                'question_id' => $item['id'],
                'answer' => $item['answer'],
            ]);
        }

        UserSetting::where('user_id', $userId)
            ->where('key', $dataForReset['keys']['question_hash'])->delete();

        UserSetting::create([
            'user_id' => $userId,
            'key' => $dataForReset['keys']['question_hash'],
            'value' => json_encode($hashData),
        ]);

        DB::commit();
        //ACT

        return apiFormatResponse(true, '');
    }

    public function check(Request $request, RawLog $rawLog)
    {
        //VALIDATION
        $dataForReset = config('auth.resetPassword');
        $rawLogTypes = config('additional.rawLog');
        $configUser = config('additional.users');
        $roles = $configUser['roles'];

        $rawLog->startLog($rawLogTypes['resetPwdQCheck'], 0, [], true);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'questions.*.id' => 'required|integer|exists:password_questions',
            'questions.*.answer' => 'required|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            throw new ApiException('common.required_parameter_is_not_included');
        }

        //find by email or username
        $company = $request->currentCompany;
        $user = User::where('company_id', $company->id)
            ->where('role_id', $roles['player'])
            ->where(function ($query) use ($request) {
                $query->where('username', $request->user_id)
                    ->orWhere('email', $request->user_id);
            })
            ->first();

        if (is_null($user)) {
            throw new ApiException('reset_password.some_wrong');
        }

        $hashData = UserSetting::where('user_id', $user->id)
            ->where('key', $dataForReset['keys']['question_hash'])->first();

        if (is_null($hashData)) {
            throw new ApiException('reset_password.some_wrong', ['problem' => 'no_hash_data']);
        }

        //check
        $hashDataValue = json_decode($hashData->value);
        $questions = collect($request->questions)->sortBy('id')->toArray();
        $possibleHash = $user->id . count($questions) . self::$KEY . json_encode(array_column($questions, 'id'));

        //check hash questions
        if (!Hash::check($possibleHash, $hashDataValue->hash)) {
            throw new ApiException('reset_password.some_wrong', ['problem' => 'hash_problem']);
        }

        //check attempts
        $attempts = (int)$hashDataValue->attempts + 1;
        if ($hashDataValue->attempts >= config('additional.resetQuestion.block_attempts')) {
            $currentDate = new DateTime();
            $dateCheckCode = new DateTime($hashDataValue->date);
            $activeCodeSeconds = config('additional.resetQuestion.block_times');
            $dateCheckCode->modify("+{$activeCodeSeconds} seconds");
            if ($currentDate > $dateCheckCode) {
                $attempts = 1;
            } else {
                throw new ApiException('reset_password.q_you_have_attempts', ['attempts' => $hashDataValue->attempts]);
            }
        }
        //check attempts


        //check answers
        $answers = UserAnswer::where('user_id', $user->id)
            ->orderBy('question_id')->get()->toArray();

        $statusChecking = $this->checkByStrict($request->questions, $answers);

        if (!$statusChecking) {
            //to do some error
            $hashData->value = [
                'date' => now()->toDateTimeString(),
                'hash' => $hashDataValue->hash,
                'attempts' => $attempts,
            ];
            $hashData->save();
            throw new ApiException('reset_password.q_incorrect_answers');
        }
        //VALIDATION

        //ACT
        //to do make hash for resetting pwd
        $sessionToken = genSessionToken();
        $keepData = [
            'session_hash' => Hash::make($sessionToken),
            'date' => now()->toDateTimeString(),
        ];

        DB::beginTransaction();

        UserSetting::where('user_id', $user->id)
            ->where('key', $dataForReset['keys']['question_session'])->delete();

        UserSetting::create([
            'user_id' => $user->id,
            'key' => $dataForReset['keys']['question_session'],
            'value' => json_encode($keepData),
        ]);

        DB::commit();
        //ACT

        $response = apiFormatResponse(true, '', [
            'session_token' => $sessionToken,
        ]);

        $rawLog->endLog($response);

        return $response;
    }

    public function reset(Request $request, RawLog $rawLog)
    {
        //VALIDATION
        $company = $request->currentCompany;
        $configUser = config('additional.users');
        $roles = $configUser['roles'];

        $rawLogTypes = config('additional.rawLog');
        $rawLog->startLog($rawLogTypes['resetPass'], 0, [], true);
        $dataForReset = config('auth.resetPassword');

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'session_token' => 'required|string',
            'password' => 'required|string',
            'confirm_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ApiException('common.required_parameter_is_not_included');
        }

        //check password
        $password = trim($request->password);
        $confirmPassword = trim($request->confirm_password);

        $checkPwd = $this->validatePassword($password);
        if (!$checkPwd) {
            throw new ApiException('registration.incorrect_password_length', [], [
                'min' => config('auth.limits.password.min'),
                'max' => config('auth.limits.password.max'),
            ]);
        }

        //check password
        if ($password != $confirmPassword) {
            throw new ApiException('registration.passwords_do_not_match');
        }

        $checkUser = User::where('company_id', $company->id)
            ->where('role_id', $roles['player'])
            ->where(function ($query) use ($request) {
                $query->where('username', $request->user_id)
                    ->orWhere('email', $request->user_id);
            })->first();

        if (!$checkUser) {
            throw new ApiException('reset_password.some_wrong');
        }

        $resetData = UserSetting::where('user_id', $checkUser->id)
            ->where('key', $dataForReset['keys']['question_session'])
            ->first();

        if (is_null($resetData)) {
            throw new ApiException('reset_password.some_wrong');
        }

        $resetDataValue = json_decode($resetData->value);

        $currentDate = new DateTime();
        $dateCheckCode = new DateTime($resetDataValue->date);
        $activeCodeSeconds = config('additional.resetQuestion.block_token_time');
        $dateCheckCode->modify("+{$activeCodeSeconds} seconds");

        if ($dateCheckCode < $currentDate) {
            throw new ApiException('reset_password.password_reset_session_has_expired');
        }

        if (!Hash::check($request->session_token, $resetDataValue->session_hash)) {
            throw new ApiException('reset_password.some_wrong');
        }
        //VALIDATION

        //ACT
        DB::beginTransaction();

        $user = User::where('id', $checkUser->id)->lockForUpdate()->first();

        User::where('id', $user->id)->update([
            'password' => Hash::make($password),
        ]);

        $hashData = UserSetting::where('user_id', $user->id)
            ->where('key', $dataForReset['keys']['question_hash'])->first();

        $hashData->value = [
            'hash' => json_decode($hashData->value)->hash,
            'attempts' => 1,
        ];
        $hashData->save();

        UserSetting::where('user_id', $user->id)
            ->where('key', $dataForReset['keys']['question_session'])->delete();

        DB::commit();
        //ACT

        $msgData = trans("api_errors/reset_password.done");
        $response = apiFormatResponse(true, $msgData['msg']);

        $rawLog->endLog($response);

        return $response;
    }

    private function checkByStrict($requestQuestions, $originQuestions)
    {
        //preparation
        $userQuestions = [];
        foreach ($requestQuestions as $requestQuestion) {
            $userQuestions[$requestQuestion['id']] = $requestQuestion['answer'];
        }

        //check
        try {
            foreach ($originQuestions as $originQuestion) {
                $originAnswer = mb_strtolower(trim($originQuestion['answer']));
                $requestAnswer = mb_strtolower(trim($userQuestions[$originQuestion['question_id']]));
                if ($originAnswer !== $requestAnswer) {
                    return false;
                }
            }
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }
}