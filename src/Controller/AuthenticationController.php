<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class AuthenticationController extends AbstractController
{
    #[Route('/authentication', name: 'authentication')]
    public function index(Request $request): Response
    {
        $email=$request->get("email");
        $password=$request->get("password");

        $client_id="9234f16667f1466297a45d2b7b35a728";
        $client_secret="014915599e0f4e7c9c63008bdf5e3d4a";

        $headers  = ['Authorization: Basic '.base64_encode($client_id.':'.$client_secret)];
        $url      = 'https://accounts.spotify.com/api/token';
        $options  = [CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_POST           => TRUE,
                    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                    CURLOPT_HTTPHEADER     => $headers];
        $credentials = $this->callSpotifyApi($options); 
        if(isset($credentials->error)){
            header('Location: /login');
            return $this->render('login.html.twig', [
                'controller_name' => 'AppController',
                'error' => $credentials->error,
            ]);
        }
        if(isset($credentials->access_token)){
            session_start();
            $_SESSION["token"] = $credentials->access_token;
            return $this->render('spotify/releases.html.twig', [
                'controller_name' => 'SpotifyController',
            ]);
        }
    }

    public function callSpotifyApi($options) 
    {
        $curl  = curl_init();
        curl_setopt_array($curl, $options); 
        $json  = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            return ['error'   => TRUE,
                    'message' => $error];
        }
        $data  = json_decode($json);
        if (is_null($data)) {
            return ['error'   => TRUE,
                    'message' => json_last_error_msg()];
        }
        return $data; 
    }
}
