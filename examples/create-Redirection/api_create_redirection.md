How to get web hosting capabilities using php wrapper?
----------------------------------------------------

This documentation will help you to create an HTTP redirection from a subdomain to another domain. Following script include DNS record check and delete record if their will conflict with your redirection!

## Requirements

- Having PHP 5.2+
- Having a dns record at OVH

## Download PHP wrapper

- Download the latest release **with dependencies** on github: https://github.com/ovh/php-ovh/releases

```bash
# When this article was written, latest version is 2.0.0
wget https://github.com/ovh/php-ovh/releases/download/v2.0.0/php-ovh-2.0.0-with-dependencies.tar.gz
```

- Extract it into a folder

```bash
tar xzvf php-ovh-2.0.0-with-dependencies.tar.gz 
```

- Create a new token
You can create a new token using these url: [https://api.ovh.com/createToken/?GET=/domain/zone/*&POST=/domain/zone/*&DELETE=/domain/zone/*](https://api.ovh.com/createToken/?GET=/domain/zone/*&POST=/domain/zone/*&DELETE=/domain/zone/*). Keep application key, application secret and consumer key to complete the script.

Be warn, this token is only validated for this script and to use **/domain/zone/\*** APIs.
If you need a more generic token, you had to change right field.

- Create php file to create your new HTTP redirection. You can download [this file](https://github.com/ovh/php-ovh/blob/master/examples/create-Redirection/apiv6.php)

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;

// Informations about your application
$applicationKey = "your_app_key";
$applicationSecret = "your_app_secret";
$consumer_key = "your_consumer_key";

// Information about API and rights asked
$endpoint = 'ovh-eu';

// Information about your domain and redirection
$domain = 'yourdomain.ovh';
$subDomain = 'www'; // Here, the redirection will come from www.yourdomain.com
$targetDomain = 'my_target.ovh';
$type = 'visible'; // can be "visible", "invisible", "visiblePermanent"

// Field to complete in case of invisible redirection
$title = '';
$keywords = '';
$description = '';

// Get servers list
$conn = new Api(    $applicationKey,
                    $applicationSecret,
                    $endpoint,
                    $consumer_key);

try {

        // check if dns record are available
        $recordIds = $conn->get('/domain/zone/' . $domain . '/record');

        foreach ($recordIds as $recordId) {
                $record = $conn->get('/domain/zone/' . $domain . '/record/' . $recordId);

                // If record include A, AAAA or CNAME for subdomain asked, we delete it
                if (    strcmp( $record['subDomain'], $subDomain) === 0 &&
                        in_array( $record['fieldType'], array( 'A', 'AAAA', 'CNAME' ) ) ) {

                        echo "We will delete field " . $record['fieldType'] . " for " . $record['zone'] . PHP_EOL;
                        $conn->delete('/domain/zone/' . $domain . '/record/' . $recordId);
                }
        }

        // We apply zone changes
        $conn->post('/domain/zone/' . $domain . '/refresh');

        // Now, we are ready to create our new redirection
        $redirection = $conn->post('/domain/zone/' . $domain . '/redirection', array(
                'subDomain'     => $subDomain,
                'target'        => $targetDomain,
                'type'          => $type,
                'title'         => $title,
                'description'   => $description,
                'keywords'      => $keywords,
        ));

        print_r( $redirection );

} catch ( Exception $ex ) {
        print_r( $ex->getMessage() );
}
?>
```

## Run php file

```bash
php apiv6.php
```

For instance, with example values in script, the answer is
```
(
    [zone] => yourdomain.ovh
    [description] => 
    [keywords] => 
    [target] => my_target.ovh
    [id] => 1342424242
    [subDomain] => www
    [type] => visible
    [title] => 
)
```

## What's more?

You can discover all domain possibilities by using API console to show all available endpoints: [https://api.ovh.com/console](https://api.ovh.com/console)

