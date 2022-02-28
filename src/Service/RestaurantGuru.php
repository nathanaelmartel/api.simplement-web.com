<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\Security;

class RestaurantGuru
{
    private $em;
    private $history;
    private $setting;
    private $logger;
    private $user;
    private $token;

    public function __construct(
        EntityManagerInterface $em,
        //HistoryService $history,
        //Setting $setting,
        LoggerInterface $guruLogger,
        Security $security
    ) {
        $this->em = $em;
        //$this->history = $history;
        //$this->setting = $setting;
        $this->logger = $guruLogger;
        $this->user = $security->getUser();
        $this->client = new \Symfony\Component\BrowserKit\HttpBrowser();
    }

    public function getReviews(string $restaurant_url, int $nb_pages = 1): array
    {
        $crawler = $this->client->request(
            'GET',
            $restaurant_url,
            [],
            [],
            [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64; rv:97.0) Gecko/20100101 Firefox/97.0',
                'HTTP_REFERER' => $restaurant_url,
            ]
        );

        $this->logger->info(sprintf(
            '[User::%s] GET %s (%s)',
            ($this->user) ? $this->user->getId() : 'null',
            $restaurant_url,
            $this->client->getResponse()->getStatusCode()
        ));
        /*  $this->history->add(
              'Guru call',
              null,
              $this->client->getResponse()->getStatusCode(),
              [
                  'status_code' => $this->client->getResponse()->getStatusCode(),
                  'content' => (string) $this->client->getResponse()->getContent(),
                  'headers' => $this->client->getResponse()->getHeaders(),
              ]
          );*/

        $reviews = [];
        for ($i = 1; $i <= $nb_pages; ++$i) {
            sleep($i * 15);
            if (1 === $i) {
                $url = sprintf('%s/reviews?bylang=1', $restaurant_url);
            } else {
                $url = sprintf('%s/reviews/%s?bylang=1', $restaurant_url, $i);
            }
            $crawler = $this->client->request(
                'GET',
                $url,
                [],
                [],
                [
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                    'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64; rv:97.0) Gecko/20100101 Firefox/97.0',
                    'HTTP_REFERER' => $restaurant_url,
                ]
            );

            $html = json_decode($this->client->getResponse()->getContent(), true)['html'];

            $this->logger->info(sprintf(
                '[User::%s] GET %s (%s)',
                ($this->user) ? $this->user->getId() : 'null',
                $url,
                $this->client->getResponse()->getStatusCode()
            ));
            /*  $this->history->add(
                  'Guru call',
                  null,
                  $this->client->getResponse()->getStatusCode(),
                  [
                      'status_code' => $this->client->getResponse()->getStatusCode(),
                      'content' => $html,
                      'headers' => $this->client->getResponse()->getHeaders(),
                  ]
              );*/

            $crawler = new Crawler($html);

            $htmlData = $crawler->filter('.o_review')->each(function ($review, $i) {
                return [
                    'id' => $review->attr('data-id'),
                    'score' => $review->attr('data-score'),
                    'lang' => $review->attr('data-lang_id'),
                    'user' => $review->filter('.user_info a')->each(function ($user, $i) {
                        return [
                            'url' => $user->attr('href'),
                            'name' => $user->text(),
                        ];
                    }),
                    'author' => $review->filter('.icon50')->each(function ($author, $i) {
                        return [
                            'guru_id' => $author->attr('data-author-id'),
                            'name' => $author->attr('alt'),
                            'agency' => $author->attr('data-agency-id'),
                            'image' => $author->attr('src'),
                        ];
                    }),
                    'date' => $review->filter('.user_info .grey')->each(function ($date, $i) {
                        return $date->text();
                    }),
                    'text' => $review->filter('.text_full')->each(function ($text, $i) {
                        return $text->text();
                    }),
                ];
            });

            foreach ($htmlData as $data) {
                $author = [];
                if (isset($data['user'][0], $data['author'][0])) {
                    $author = array_merge($data['user'][0], $data['author'][0]);
                } elseif (isset($data['user'][0])) {
                    $author = $data['user'][0];
                } elseif (isset($data['author'][0])) {
                    $author = $data['author'][0];
                }

                $reviews[$data['id']] = [
                    'guru_id' => $data['id'],
                    'score' => $data['score'],
                    'lang' => $data['lang'],
                    'author' => $author,
                    'date' => $this->evaluateDate($data['date'][0]),
                    'info' => $data['date'][0],
                    'network' => $this->evaluateNetwork($data['date'][0]),
                    'text' => $data['text'][0],
                ];
            }
        }
        /*
        "5_3678862" => array:8 [
            *"id" => "5_3678862"
            *"score" => "4"
            *"lang" => "1"
        *    "author" => array:5 [ …5]
            *"date" => DateTime @1640450565 {#771 …1}
            "info" => "2 mois plus tôt sur Yelp"
            *"network" => "Yelp"
            "text" => "My friends and I were lucky enough to get a reservation a month in advance for a weekday lunch. Upon entering the restaurant, the service was impeccable: the staff took our coats and shepherded us to our table in an elegant, almost minimalist, dining room. The meal itself was beautifully presented, with Japanese and French influences. The highlights for me were easily the Parmesan amuse bouche, herb foam with lobster and caviar and sorbet, and the mouth-watering miso encrusted pigeon. While the others in my party loved the famed Garden salad, I thought it was a bit heavy on the cream dressing. The desserts were beautifully made, especially the chestnut meringue - but I have to admit meringue itself isn't my favorite. At the conclusion of our meal, they allowed us a photo op with the chef, and sent us off with a signed copy of the menu and some house made caramels. Overall, the Kei experience provided a fantastic and lengthy meal that left me lethargic at the end, but impressed and satisfied with how I spent the afternoon."
          ]
          "author" => array:5 [
             *"url" => "https://fr.restaurantguru.com/link/a1:40568811:rw10303264"
        *    "name" => "Roulottes Arc En Ciel"
        *    "id" => "255836581"
        *    "agency" => "1"
        *    "image" => "https://10619-1.s.cdn12.com/img/site/new/negative.svg"
  ]

*/
        return $reviews;
    }

    private function evaluateDate(string $string): ?\DateTime
    {
        $pieces = explode(' plus tôt sur ', $string);

        $string_en = str_replace('un', '1', $pieces['0']);
        $string_en = str_replace('mois', 'month', $string_en);
        $string_en = str_replace('années', 'years', $string_en);
        $string_en = str_replace('an', 'year', $string_en);

        try {
            $date = new \DateTime('-'.$string_en);
        } catch (\Exception) {
            dd($string, $pieces, $string_en);
        }

        return $date;
    }

    private function evaluateNetwork(string $string)
    {
        $pieces = explode(' sur ', $string);

        if (isset($pieces['1'])) {
            return trim($pieces['1']);
        }

        return $string;
    }
}
