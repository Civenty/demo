<?php

namespace academy\models;

use core\models\academy\AcQuestions;
use core\models\academy\AcUsersTestProgress;
use core\models\academy\logs\AcTestsProgressLog;
use Yii;

/**
 * Class Questions
 * @package academy_frontend\models
 */
class Questions extends AcQuestions
{
    /**
     * @param Questions $questionModel
     * @param $questionNumber
     * @return array|string
     */
    public static function parseQuestionJsonData(Questions $questionModel, $questionNumber)
    {
        $decodeData = json_decode(getTranslate($questionModel, 'data'));

        $questionData = $decodeData->dynamicFields[$questionNumber];
        $data = $questionData->values;

        return [
            'type' => $data->allowCustom ? Questions::TYPE_CUSTOM : Questions::TYPE_WITH_OPTIONS,
            'title' => $data->name,
            'desc' => $data->description,
            'img' => isset($data->image) ? $data->image : null,
            'options' => $data->options,
            'correct_answer' => self::getCorrectOptionNumber($data->options),
            'question_points' => $data->points
        ];
    }

    /**
     * @param $questionsJsonData
     * @param $questionNumber
     * @return array|string
     */
    public static function parseModerationQuestionJsonData($questionsJsonData, $questionNumber)
    {
        $decodeData = json_decode($questionsJsonData);

        $questionData = $decodeData->dynamicFields[$questionNumber];
        $data = $questionData->values;

        return [
            'type' => $data->allowCustom ? Questions::TYPE_CUSTOM : Questions::TYPE_WITH_OPTIONS,
            'title' => $data->name,
            'desc' => $data->description,
            'img' => isset($data->image) ? $data->image : null,
            'options' => $data->options,
            'correct_answer' => self::getCorrectOptionNumber($data->options),
            'question_points' => $data->points
        ];
    }

    /**
     * @param Tests $testModel
     * @return mixed
     */
    public static function parseAllQuestionsJsonData(Tests $testModel)
    {
        $dbData = self::findOne(['test_id' => $testModel->id]);
        $decodeData = json_decode(getTranslate($dbData, 'data'));

        return $decodeData->dynamicFields;
    }

    /**
     * @param $optionsData
     * @return int|string
     */
    private static function getCorrectOptionNumber($optionsData)
    {
        $result = 0;

        foreach ($optionsData as $optionNumber => $optionsDatum) {
            if ($optionsDatum->correct) {
                $result = $optionNumber;
            }
        }

        return $result;
    }

    /**
     * @param Tests $testModel
     * @return int
     */
    public static function getCountOfTestQuestions(Tests $testModel)
    {
        $questionModel = static::findOne(['test_id' => $testModel->id]);
        $actualQuestionData = json_decode(getTranslate($questionModel, 'data'))->dynamicFields;

        return count($actualQuestionData);
    }

    /**
     * @param Tests $testModel
     * @return bool
     */
    public static function testAutoModerate(Tests $testModel)
    {
        $answers = AcUsersTestProgress::getUserAnswers($testModel->id);
        if(count($answers) <= 0 || AcUsersTestProgress::isHaveCustomAnswer($answers)) {
            return true;
        }
        $result = true;
        $questionModel = static::findOne(['test_id' => $testModel->id]);
        $actualQuestionData = json_decode(getTranslate($questionModel, 'data'))->dynamicFields;

        foreach ($actualQuestionData as $actualQuestionDatum) {
            if ($actualQuestionDatum->values->allowCustom) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param $answersData
     * @return int
     */
    public static function calculateCorrectAnswersQuantity($answersData)
    {
        $answers = json_decode($answersData);
        return $this->getCountOfCorrectAnswers($answers);
    }

}
