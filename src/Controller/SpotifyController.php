<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\Session;

class SpotifyController extends AbstractController
{

    private $client_id = "9234f16667f1466297a45d2b7b35a728";
    private $client_secret = "014915599e0f4e7c9c63008bdf5e3d4a";

    #[Route('/', name: 'auth')]
    public function auth()
    {
        $headers  = ['Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret)];
        $url      = 'https://accounts.spotify.com/api/token';
        $options  = [CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_POST           => TRUE,
                    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
                    CURLOPT_HTTPHEADER     => $headers];
        $credentials = $this->callSpotifyApi($options);

        $session = new Session();
        $session->start();
        $session->set('token', $credentials->access_token);

        return $this->redirectToRoute('releases');
    }


    /**
     * Pagina para visualizar el lanzamiento de album y sus respectivas canciones.
     */
    #[Route('/releases', name: 'releases')]
    public function releases(): Response
    {
        $session = new Session();
        $token = $session->get('token');
        
        /**
         * se llama la información de los estrenos colombianos
         */
        $headers  = ['Authorization: Bearer '.$token,
                    'scope: user-library-read'];
        $url      = "https://api.spotify.com/v1/browse/new-releases?country=CO&limit=10&offset=5";
        $options  = [CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_HTTPHEADER     => $headers];
        $result = $this->callSpotifyApi($options);

        if($result == "undefinded" || $result == null){
            return $this->redirectToRoute('auth');
        }

        $albums = [];  

        foreach($result->albums->items as $album){
            $artist = [];
            $info["name"] = $album->name;
            $info["img"] = $album->images[1]->url;
            foreach($album->artists as $artist){
                $artists['name'] = $artist->name;
                $artists['url'] = $artist->href;
            }
            $info["artists"] = $artists;
            $albums[] = $info;
        }

        return $this->render('spotify/releases.html.twig', [
            'controller_name' => 'SpotifyController',
            'albums' => $albums,
        ]);
        
    }

    #[Route('/artist', name: 'artist')]
    public function artist(Request $request): Response
    {   

        $url = $request->query->get('url');

        $session = new Session();
        $token = $session->get('token');

        /**
        * Se llama la información del artista
        */
        $headers  = ['Authorization: Bearer '.$token, 'scope: user-library-read'];
        $options  = [CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_HTTPHEADER     => $headers];
        $result = $this->callSpotifyApi($options);

        if($result == "undefinded" || $result == null){
            return $this->redirectToRoute('auth');
        }

        $name = $result->name;
        $img = $result->images[1]->url;
        $genres = $result->genres;
        $followers = $result->followers->total;
        $id = $result->id;

        /**
         * Se llama el top de canciones del artista
         */
        $options  = [CURLOPT_URL => 'https://api.spotify.com/v1/artists/'.$id.'/top-tracks?market=ES',
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER     => $headers];
        $tracks = $this->callSpotifyApi($options);
        
        if($result == "undefinded" || $result == null){
            return $this->redirectToRoute('auth');
        }

        $listByTrack = [];
        foreach($tracks->tracks as $key => $track){
            $listByTrack['song'] = $track->name;
            $seconds = ($track->duration_ms / 1000) % 60 ;
            $minutes = (($track->duration_ms / (1000*60)) % 60);
            $listByTrack['duration'] = $minutes.':'.$seconds;
            $listByTrack['url'] = $track->external_urls->spotify;
            foreach($track->artists as $id => $artist){
                $nameArtists[$id] = $artist->name;
            }
            $listByTrack['artists'] = $nameArtists;
            $listByTrack['imgAlbum'] = $track->album->images[2]->url;
            $listTracks[$key+1] = $listByTrack;
        }

        //dd($listTracks);

        return $this->render('spotify/artist.html.twig', [
            'controller_name' => 'SpotifyController',
            'name' => $name,
            'img' => $img,
            'genres' => $genres,
            'followers' => $followers,
            'tracks' => $listTracks
        ]);
    }

    /**
     * Funcion para realizar peticiones a spotify
     */
    public function callSpotifyApi($options) 
    {
        $curl  = curl_init();
        curl_setopt_array($curl, $options); 
        $json  = curl_exec($curl);
        $error = curl_error($curl);
        header("Access-Control-Allow-Origin: *");
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
