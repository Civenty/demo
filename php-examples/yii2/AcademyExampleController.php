<?php

namespace academy\controllers;

use academy_frontend\helpers\MetaTags;
use core\models\academy\AcCertificates;
use core\models\academy\AcCertificatesFiles;
use core\models\academy\AcUsersTestProgress;
use core\models\User;
use academy_frontend\models\Tests;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use yii\web\NotFoundHttpException;
use academy_frontend\models\Questions;
use core\models\academy\logs\AcTestsProgressLog;
use academy_frontend\helpers\traits\CourseTestsAccessTrait;
use academy_frontend\models\forms\QuestionWithCustomForm;
use academy_frontend\models\forms\QuestionWithOptionsForm;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use yii\web\Response;

/**
 * Class TestsController
 * @package academy_frontend\controllers
 */
class AcademyExampleController extends AcademyController
{
    /**
     * Holds logic to interact with user
     * common/traits/academy/CourseTestsAccessTrait
     */
    use CourseTestsAccessTrait;

    /**
     * Template constants, need to render views
     */
    const TEMPLATE_WITH_OPTIONS = 'with-options';
    const TEMPLATE_WITH_CUSTOM  = 'custom-answer';

    /**
     * Flag and constants
     */
    const MODERATE_PROCESS_PAGE    = 0;
    const MODERATE_CALCULATED_PAGE = 1;
    const FIRST_QUESTION_NUMBER    = 1;

    /**
     * Hold elapsed time for answer on test question
     * @var $timer string
     */
    private $timer;

    /**
     * Bind yii2 layout view
     * @var string
     */
    public $layout = "tests";

    /**
     * Usefull variables
     * @var $questionForm QuestionWithCustomForm | QuestionWithOptionsForm
     * @var $questionView string
     * @var $correctAnswer
     * @var $questionPoints
     * @var $testType integer
     * @var $firstQuestionUrl string
     */
    private $questionForm,
        $questionView,
        $correctAnswer,
        $questionPoints,
        $testType,
        $firstQuestionUrl;

    /**
     * @param null $courseAlias
     * @param $testAlias
     * @return \SimpleHtmlDom\simple_html_dom|string
     */
    public function actionTestMain($courseAlias = null, $testAlias)
    {
        $this->proceedUserTestLog();
        $this->setFirstQuestionUrl();
        $this->setTestType();

        $metaTags = new MetaTags();
        $metaTags->setTags($this->test, '/tests/test', getTranslate($this->test, 'title'));
        return $this->render('test-page', [
            'test' => $this->test,
            'course' => $this->course,
            'testType' => $this->testType,
            'firstQuestionUrl' => $this->firstQuestionUrl
        ]);
    }

    /**
     * @param null $courseAlias
     * @param $testAlias
     * @param $questionNumber
     * @return \SimpleHtmlDom\simple_html_dom|string
     */
    public function actionTestQuestion($courseAlias = null, $testAlias, $questionNumber)
    {
        $this->initUserTestLog($questionNumber);
        $this->proceedUserTestLog($questionNumber);
        $questionData = $this->getQuestionData($questionNumber);
        $this->bindQuestionInitData($questionData['type']);
        $this->handleQuestionForm();
        $this->setTestType();

        $metaTags = new MetaTags();
        $metaTags->setTags($this->test, '/tests/question', $questionData['title']);
        return $this->render($this->questionView, [
            'test' => $this->test,
            'question' => $questionData,
            'testType' => $this->testType,
            'formModel' => $this->questionForm,
            'questionNumber' => $questionNumber,
            'course' => $courseAlias ? $this->course : null,
            'timer' => $this->timer
        ]);
    }

    /**
     * @param null $courseAlias
     * @param $testAlias
     * @return \SimpleHtmlDom\simple_html_dom|string
     */
    public function actionModerated($courseAlias = null, $testAlias)
    {
        $logModel = $this->getLogModel();

        return $this->render('moderated', [
            'course' => $this->course,
            'test' => $this->test,
            'log' => $logModel
        ]);
    }

    /**
     * @param null $courseAlias
     * @param $testAlias
     * @return \SimpleHtmlDom\simple_html_dom|string
     */
    public function actionNotModerated($courseAlias = null, $testAlias)
    {
        return $this->render('not_moderated', ['course' => $this->course]);
    }

    /**
     * @param $questionNumber
     * @return array|string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    private function getQuestionData($questionNumber)
    {
        $arrayQuestionKey = $questionNumber - 1;

        $questionsModel = Questions::findOne(['test_id' => $this->test->id]);
        if (!$questionsModel) {
            throw new NotFoundHttpException();
        }

        $preparedQuestion = Questions::parseQuestionJsonData($questionsModel, $arrayQuestionKey);
        if (!$preparedQuestion) {
            throw new InvalidParameterException();
        }

        $this->correctAnswer = $preparedQuestion['correct_answer'];
        $this->questionPoints = $preparedQuestion['question_points'];
        return $preparedQuestion;
    }

    /**
     * @return string|\yii\web\Response
     */
    protected function handleQuestionForm()
    {
        if ($this->questionForm->load(\Yii::$app->request->post())) {
            $this->questionForm->correct_answer = $this->correctAnswer;
            $this->questionForm->points = $this->questionPoints;
            if ($this->questionForm->save()) {
                $nextQuestionUrl = \Yii::$app->request->post('nextQuestionUrl');
                $this->updateUserTestLog($nextQuestionUrl);
            }
        }
    }

    /**
     * Bind question form and view template
     * @param $type
     */
    protected function bindQuestionInitData($type)
    {
        if ($type == Questions::TYPE_WITH_OPTIONS) {
            $this->questionForm = new QuestionWithOptionsForm();
            $this->questionView = self::TEMPLATE_WITH_OPTIONS;
        } else {
            $this->questionForm = new QuestionWithCustomForm();
            $this->questionView = self::TEMPLATE_WITH_CUSTOM;
        }
    }

    /**
     * Init Log
     * @param $questionNumber
     */
    protected function initUserTestLog($questionNumber)
    {
        $logModel = $this->getLogModel();
        $this->initTimer($logModel);
        if (!$logModel && $questionNumber == self::FIRST_QUESTION_NUMBER) {
            AcTestsProgressLog::initializeLog(
                $this->test,
                $this->course,
                self::FIRST_QUESTION_NUMBER);

        }

        if (!$logModel && $questionNumber != self::FIRST_QUESTION_NUMBER) {
            $this->proceedUserTestLog();
        }
    }

    /**
     * @param null $questionNumber
     * @param null $logModel
     * @return \yii\web\Response
     */
    protected function proceedUserTestLog($questionNumber = null, $logModel = null)
    {
        $logModel = !$logModel ? $this->getLogModel() : $logModel;

        if (!$logModel && $questionNumber) {
            return $this->redirect([$this->getRedirectUrl()]);
        }

        if ($logModel) {
            switch ($logModel->status) {
                case AcTestsProgressLog::STATUS_PROCESS:
                    $this->questionRedirectUrlBuilder($logModel->user_questions, $questionNumber, $this->getRedirectUrl());
                    break;
                case AcTestsProgressLog::STATUS_COMPLETE:
                    $this->getModerationPage($logModel);
                    break;
                default:
                    throw new InvalidParameterException();
            }
        }
    }

    /**
     * Update user test question progress log
     * @param $nextUrl
     * @return \yii\web\Response
     */
    protected function updateUserTestLog($nextUrl)
    {
        $logModel = $this->getLogModel();

        if ($logModel->test_questions == $logModel->user_questions) { //user passed all questions
            $autoModerateStatus = Questions::testAutoModerate($this->test);
            $logModel->mod_status = $autoModerateStatus
                ? AcTestsProgressLog::AUTO_MODERATE_TRUE
                : AcTestsProgressLog::AUTO_MODERATE_FALSE;
            $logModel->status = AcTestsProgressLog::STATUS_COMPLETE;
            $logModel->passed_at = time();

            $this->accruePointsToUser($autoModerateStatus, $logModel);
            $this->getModerationPage($logModel);
        } else {
            $logModel->user_questions++;
            $logModel->update();
            return $this->redirect([$nextUrl]);
        }
    }

    /**
     * @param bool $actual
     * @param bool $complete
     * @return array|null|AcTestsProgressLog
     */
    protected function getLogModel($actual = true, $complete = false)
    {
        $logModel = AcTestsProgressLog::find()->where(['user_id' => \Yii::$app->user->id])
            ->andWhere(['test_id' => $this->test->id]);
        if ($this->course) {
            $logModel->andWhere(['course_id' => $this->course->id]);
        }
        if ($actual) {
            $logModel->andWhere(['actual_status' => AcTestsProgressLog::STATUS_ACTUAL]);
        }
        if ($complete) {
            $logModel->andWhere(['status' => AcTestsProgressLog::STATUS_COMPLETE]);
        }

        return $logModel->one();
    }

    /**
     * @param $userQuestion
     * @param $questionNumber
     * @param $redirectUrl
     * @return \yii\web\Response
     */
    protected function questionRedirectUrlBuilder($userQuestion, $questionNumber, $redirectUrl)
    {
        if ($userQuestion != $questionNumber) {
            return $this->redirect([$redirectUrl . "/question{$userQuestion}"]);
        }
    }

    /**
     * Accure test points to user site rating
     * @param $autoModerateStatus
     * @param $logModel AcTestsProgressLog
     * @internal param $points
     */
    protected function accruePointsToUser($autoModerateStatus, $logModel)
    {
        if ($autoModerateStatus) {
            $logModel->points = AcUsersTestProgress::getUserTotalPoints($this->test, $logModel);
            if ($logModel->points > 0) {
                $user = User::findOne([\Yii::$app->user->id]);
                $user->site_rating += $logModel->points;
                $user->update();
                $this->generateCertificate($logModel->points);
            }
        } else {
            AcUsersTestProgress::mergeTestsData($logModel, $this->test);
        }

        $logModel->update();
    }

    /**
     * @param $logModel AcTestsProgressLog
     * @param bool $forResponse
     * @return string|Response
     */
    protected function getModerationPage($logModel = null, $forResponse = false)
    {
        $autoStatus = $logModel->mod_status == AcTestsProgressLog::AUTO_MODERATE_TRUE;
        if ($forResponse) {
            return $this->getRedirectUrl() . ($autoStatus ? "/moderated" : "/not-moderated");
        }
        return $this->redirect([$this->getRedirectUrl() . ($autoStatus ? "/moderated" : "/not-moderated")]);
    }

    /**
     * Bind main test properties
     */
    protected function setFirstQuestionUrl()
    {
        $this->firstQuestionUrl = $this->course
            ? "/course-test/{$this->course->alias}/{$this->test->alias}/question1"
            : "/test/{$this->test->alias}/question1";
    }

    /**
     * Set testType property
     */
    protected function setTestType()
    {
        $this->testType = $this->course ? Tests::PRIVATE_TEST : Tests::PUBLIC_TEST;
    }

    /**
     * @return string
     */
    protected function getRedirectUrl()
    {
        return !$this->course
            ? "/test/{$this->test->alias}"
            : "/course-test/{$this->course->alias}/{$this->test->alias}";
    }

    /**
     * @param null $courseAlias
     * @param $testAlias
     * @return array
     */
    public function actionAjaxEndTest($courseAlias = null, $testAlias)
    {
        if (!\Yii::$app->request->isAjax) {
            throw new MethodNotAllowedException(['ajax']);
        }

        \Yii::$app->response->format = Response::FORMAT_JSON;
        $logModel = $this->getLogModel();

        $autoModerateStatus = Questions::testAutoModerate($this->test);
        $logModel->mod_status = $autoModerateStatus
            ? AcTestsProgressLog::AUTO_MODERATE_TRUE
            : AcTestsProgressLog::AUTO_MODERATE_FALSE;
        $logModel->status = AcTestsProgressLog::STATUS_COMPLETE;
        $logModel->passed_at = time();

        $this->accruePointsToUser($autoModerateStatus, $logModel);
        $url = $this->getModerationPage($logModel, true);

        return [
            'res' => 'ok',
            'url' => $url
        ];
    }

    /**
     * Initialize test time if its required
     * @param $logModel AcTestsProgressLog
     */
    private function initTimer($logModel)
    {
        if (!$logModel) {
            if ($this->test->timer_mins > 0) {
                $this->timer = ($this->test->timer_mins < 10) ? ("0" . $this->test->timer_mins) : $this->test->timer_mins;
                $this->timer .= ":00";
            } else {
                $this->timer = null;
            }
        } else {
            $this->updateTimer($logModel);
        }

    }

    /**
     * Update timer and closer test if time expired
     * @param $logModel AcTestsProgressLog
     */
    private function updateTimer($logModel)
    {
        if ($this->test->timer_mins > 0) {
            $now = time();
            $startTime = $logModel->start_at;
            $minutes = round(abs($now - $startTime) / 60,2);
            $seconds = abs($now - $startTime) % 60;

            $this->timer = $this->calculateElapsedTime($minutes, $seconds);
        }
    }

    /**
     * @param $totalPoints
     */
    private function generateCertificate($totalPoints)
    {
        if ($certificates = AcCertificates::findAll(['course_id' => $this->course->id])) {
            AcCertificatesFiles::proceedCertificatePdf($certificates, $totalPoints, $this->course->id, \Yii::$app->user->id);
        }
    }
}
