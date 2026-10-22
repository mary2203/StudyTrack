<?php

namespace Tests\Unit;

use App\Models\AcademicSemester;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\CoursePolicy;
use Tests\TestCase;

class ClassroomReadOnlyPolicyTest extends TestCase
{
    public function test_classroom_courses_cannot_be_edited_or_deleted(): void
    {
        $user = new User();
        $user->id = 7;
        $course = new Course(['classroom_course_id' => 'google-course']);
        $course->setRelation('semester', new AcademicSemester(['user_id' => $user->id]));

        $policy = new CoursePolicy();

        $this->assertFalse($policy->update($user, $course));
        $this->assertFalse($policy->delete($user, $course));
    }

    public function test_classroom_activities_are_read_only_but_local_activities_remain_editable(): void
    {
        $user = new User();
        $user->id = 7;
        $policy = new ActivityPolicy();
        $synced = new Activity(['user_id' => $user->id, 'classroom_coursework_id' => 'google-work']);
        $local = new Activity(['user_id' => $user->id]);

        $this->assertFalse($policy->update($user, $synced));
        $this->assertFalse($policy->delete($user, $synced));
        $this->assertTrue($policy->update($user, $local));
        $this->assertTrue($policy->delete($user, $local));
    }
}
