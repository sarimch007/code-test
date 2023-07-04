<?php

namespace app\Http\Controllers;

use app\Models\Job;
use app\Http\Requests;
use app\Models\Distance;
use Illuminate\Http\Request;
use app\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    const ADMIN_ROLE_ID = 1;
    const SUPERADMIN_ROLE_ID = 2;

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response = null;
        $allowedRoles = [self::ADMIN_ROLE_ID, self::SUPERADMIN_ROLE_ID];
        if ($user_id = $request->input('user_id')) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif (in_array($request->__authenticatedUser->user_type, $allowedRoles)) {
            $response = $this->repository->getAll($request);
        }
        return response()->json($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response()->json($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->only(['field1', 'field2', 'field3']);
        $authenticatedUser = $request->__authenticatedUser;
    
        return response($this->repository->store($authenticatedUser, $data));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($user_id = $request->input('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->only(['field1', 'field2', 'field3']);
        return response($this->repository->acceptJob($data, $request->__authenticatedUser));
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);
    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
{
    $data = $request->all();

    $distance = $data['distance'] ?? "";
    $time = $data['time'] ?? "";
    $jobid = $data['jobid'] ?? "";
    $session = $data['session_time'] ?? "";

    $flagged = !!$data['flagged'] ? 'yes' : 'no';
    $manually_handled = !!$data['manually_handled'] ? 'yes' : 'no';
    $by_admin = !!$data['by_admin'] ? 'yes' : 'no';

    $admincomment = $data['admincomment'] ?? null;

    if ($time || $distance) {
        $affectedRows = Distance::where('job_id', $jobid)->update([
            'distance' => $distance,
            'time' => $time,
        ]);
    }

    if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
        $affectedRows1 = Job::where('id', $jobid)->update([
            'admin_comments' => $admincomment,
            'flagged' => $flagged,
            'session_time' => $session,
            'manually_handled' => $manually_handled,
            'by_admin' => $by_admin,
        ]);
    }

    return response()->json(['message' => 'Record updated!']);
}


    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }
}
