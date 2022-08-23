<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\User\Application\DTO\UserData;
use Src\User\Domain\Factories\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\WithLogin;

class UserTest extends TestCase
{
    use RefreshDatabase, WithLogin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user_uri = '/user';
        $this->index_uri = $this->user_uri . '/index';
        $this->random_avatar_uri = $this->user_uri . '/random-avatar';
        $this->adminToken = $this->adminLoginAndGetToken();
        $this->userToken = $this->userLoginAndGetToken();
    }

    /** @test */
    public function admin_can_retrieve_all_users()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->get($this->index_uri)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonCount($numberUsers + 2); // +2 because of the admin and user
    }

    /** @test */
    public function user_cannot_retrieve_all_users()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->get($this->index_uri)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee(['error' => 'The user is not authorized to access this resource']);
    }

    /** @test */
    public function admin_can_get_specific_user_by_id()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->get($this->user_uri . '/' . $randomUserId)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['id', 'name', 'email', 'avatar', 'is_admin', 'is_active']);
    }

    /** @test */
    public function user_cannot_get_specific_user_by_id()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->get($this->user_uri . '/' . $randomUserId)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee(['error' => 'The user is not authorized to access this resource']);
    }

    /** @test */
    public function admin_can_create_a_user()
    {
        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => 'https://doodleipsum.com/300/avatar-2?shape=circle',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $expectedResponse = [
            'id' => 3,
            'name' => $requestBody['name'],
            'email' => $requestBody['email'],
            'is_admin' => false,
            'is_active' => true
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->post($this->user_uri, $requestBody)
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJson($expectedResponse);

        // Assert cannot create user with same email
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->post($this->user_uri, $requestBody)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'El email ya está en uso']);
    }

    /** @test */
    public function user_cannot_create_user()
    {
        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => 'https://doodleipsum.com/300/avatar-2?shape=circle',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->post($this->user_uri, $requestBody)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee(['error' => 'The user is not authorized to access this resource']);
    }


    /** @test */
    public function cannot_create_user_with_invalid_email()
    {
        $password = $this->faker->password(8);
        $requestBodyInvalidEmail = [
            'name' => $this->faker->name,
            'email' => 'invalid-email',
            'avatar' => 'https://doodleipsum.com/300/avatar-2?shape=circle',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->post($this->user_uri, $requestBodyInvalidEmail)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'Must be a valid email']);
    }

    /** @test */
    public function cannot_create_user_with_invalid_password()
    {
        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => 'https://doodleipsum.com/300/avatar-2?shape=circle',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $requestBodyInvalidPassword = $requestBody;
        $requestBodyInvalidPassword['password'] = '1234';
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->post($this->user_uri, $requestBodyInvalidPassword)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'The password needs to be at least 8 characters long']);

        $requestBodyNoPasswordConfirmation = $requestBody;
        unset($requestBodyNoPasswordConfirmation['password_confirmation']);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->post($this->user_uri, $requestBodyNoPasswordConfirmation)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'Passwords do not match']);
    }

    /** @test */
    public function admin_can_update_any_user()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => false,
            'is_active' => false,
            'update_avatar' => true,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $expectedResponse = [
            'id' => $randomUserId,
            'name' => $requestBody['name'],
            'email' => $requestBody['email'],
            'avatar' => false,
            'is_admin' => false,
            'is_active' => false
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->patch($this->user_uri . '/' . $randomUserId, $requestBody)
            ->assertStatus(Response::HTTP_OK)
            ->assertJson($expectedResponse);
    }

    /** @test */
    public function user_cannot_update_any_user_except_itself()
    {
        $userCredentials = $this->validCredentials(['is_admin' => false]);
        $userToken = $this->getToken($this->post('auth/login', $userCredentials));

        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(4, 4 + $numberUsers);

        // Update another user
        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => false,
            'is_active' => false,
            'update_avatar' => true,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $userToken])
            ->patch($this->user_uri . '/' . $randomUserId, $requestBody)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee(['error' => 'The user is not authorized to access this resource']);

        // Update itself
        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $userCredentials['email'],
            'avatar' => false,
            'is_active' => true,
            'update_avatar' => false,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $expectedResponse = [
            'id' => 3,
            'name' => $requestBody['name'],
            'email' => $requestBody['email'],
            'avatar' => $requestBody['avatar'],
            'is_active' => $requestBody['is_active']
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . $userToken])
            ->patch($this->user_uri . '/' . 3, $requestBody)
            ->assertStatus(Response::HTTP_OK)
            ->assertJson($expectedResponse);
    }

    /** @test */
    public function cannot_update_user_with_invalid_password()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $password = $this->faker->password(8);
        $requestBody = [
            'name' => $this->faker->name,
            'email' => $this->faker->safeEmail,
            'avatar' => 'https://doodleipsum.com/300/avatar-2?shape=circle',
            'is_active' => true,
            'update_avatar' => true,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $requestBodyInvalidPassword = $requestBody;
        $requestBodyInvalidPassword['password'] = '1234';
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->patch($this->user_uri . '/' . $randomUserId, $requestBodyInvalidPassword)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'The password needs to be at least 8 characters long']);

        $requestBodyNoPasswordConfirmation = $requestBody;
        unset($requestBodyNoPasswordConfirmation['password_confirmation']);
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->patch($this->user_uri . '/' . $randomUserId, $requestBodyNoPasswordConfirmation)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['error' => 'Passwords do not match']);
    }

    /** @test */
    public function admin_can_delete_a_user()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->delete($this->user_uri . '/' . $randomUserId)
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->get($this->user_uri . '/' . $randomUserId)
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function user_cannot_delete_a_user()
    {
        $numberUsers = $this->faker->numberBetween(1, 10);
        $this->createRandomUsers($numberUsers);
        $randomUserId = $this->faker->numberBetween(1, $numberUsers);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->delete($this->user_uri . '/' . $randomUserId)
            ->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee(['error' => 'The user is not authorized to access this resource']);
    }

    /** @test */
    public function cannot_delete_user_if_does_not_exists()
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->delete($this->user_uri . '/' . 999)
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /** @test */
    public function can_get_random_image() // TODO - Mock HTTP request
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->get($this->random_avatar_uri)
            ->assertStatus(Response::HTTP_OK);
    }

    private function createRandomUsers($usersNumber = 1): void
    {
        foreach (range(1, $usersNumber) as $_) {
            $user = UserFactory::new();
            $userEloquentModel = UserData::toEloquent($user);
            $userEloquentModel->password = $this->faker->password(8);
            $userEloquentModel->save();
        }
    }
}