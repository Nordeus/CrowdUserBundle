
CrowdUserBundle
========================

CrowdUserBundle is Symfony bundle used for fetching users and authenticating them against Atlassian Crowd.

---

Installation and setup
---

### 1. Clone this repository using composer

Execute the following in your project's root directory:

```
composer require nordeus/crowd-user-bundle
```

#### If you are using Symfony Flex

The bundle has automatically been registered and configured for you!
Simply use config/packages/security.yaml.example as a starting point to configure your security.yaml file, and skip to step 6.

### 2. Enable the bundle in the kernel

```
<?php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Nordeus\CrowdUserBundle\NordeusCrowdUserBundle(),
    );
}
```

### 3. Import CrowdUserBundle routing file

```
# app/config/routing.yaml
crowd_user:
    resource: "@NordeusCrowdUserBundle/Resources/config/routing.yaml"
```

If you intend to manually override some of CrowdUserBundle's routes (see step 6), make sure you include this _after_ your own bundle's config.

### 4. Configure parameters

These are required params:

```
# app/config/config.yaml
nordeus_crowd_user:
    crowd_application_name: crowd_application_name
    crowd_service_url: http://crowd.your_domain.com:8095
    sso_cookie_domain: your_domain.com
    crowd_application_password: crowd_application_password
    roles_to_groups:
        role1: [ groupA, groupB ]
        role2: [ groupD, groupE ]
```

Set up your *roles_to_groups* map by providing a list of Crowd groups for each Symfony role that users in those groups should receive.
An example:

```
# app/config/config.yaml
nordeus_crowd_user:
    # ...
    roles_to_groups:
        ROLE_USER:
            - users
        ROLE_ADMIN:
            - superadmins
            - developers
```

There are also optional params, which have default values. These params are:

```
# app/config/config.yaml
nordeus_crowd_user:
    # ...
    user_class: Nordeus\CrowdUserBundle\Security\User\CrowdUser
    sso_cookie_name: crowd.token_key
    service_uri: /crowd/rest/usermanagement/1/
    curl_timeout: 2
    connection_retries: 2
    user_refresh_time: 600 # seconds
```

If you want to extend CrowdUser, set *user_class* parameter with your user class name as a value.

### 5. Configure your application's security

```
# app/config/security.yaml
security:
    role_hierarchy:
        ROLE_ADMIN: ROLE_USER

    providers:
        crowd_provider:
            id: crowd.user_provider

    firewalls:
        main:
            pattern: ^/
            crowd_form_login:
                login_path: nordeus_crowd_user_login
                check_path: nordeus_crowd_user_login_check
                default_target_path: /
            crowd_sso: true
            logout:
                path: nordeus_crowd_user_logout
            anonymous: ~
            access_denied_handler: crowd.access_denied_handler

    access_control:
        - { path: ^/(_(profiler|wdt)|img|css|js|login), role: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, role: ROLE_USER }
```

The *default_target_path* key is optional and may be reconfigured, it is the path where the user will be redirected after a successful login.

The *access_denied_handler* key is optional, and will cause *access denied* errors to redirect to the login page with an error message instead of breaking the request.

### 6. (optional) Override some of CrowdUserBundle's functionality

Suppose you want to override a controller (change some of the business logic) or a resource (template, routing, etc) from CrowdUserBundle.
There are two possible approaches:

#### A. Bundle inheritance

When set up, any matching files in your bundle will automatically override files in CrowdUserBundle.
Read the basics of setting up bundle inheritance and follow the instructions [**here**](http://symfony.com/doc/current/cookbook/bundles/inheritance.html).

#### B. Manual re-routing

In your own bundle, define a route using an existing CrowdUserBundle path, just have it target one of your own actions.
If you need some part of the original functionality, extend the original controller/template.

### 7. Clear the Symfony cache

```
php app/console cache:clear
```

---

TODO
---

* Simulate or disable authentication in test environment

---

Explanation
---

Symfony Authentication is based on the following concept:

In security section (app/config/security.yaml) you have to define a firewall.
The most common case is that you define only one firewall - **main** firewall.
Here is en example:

```
# app/config/security.yaml
security:
    firewalls:
        main:
            pattern: ^/
            crowd_form_login:
                login_path: nordeus_crowd_user_login
                check_path: nordeus_crowd_user_login_check
                default_target_path: /
            crowd_sso: true
            logout:
                path: nordeus_crowd_user_logout
            anonymous: ~
            access_denied_handler: crowd.access_denied_handler
```

Firewall implementation works like this: The firewall listens to the *onKernelRequest* event.
When an event is triggered, it uses the firewall map to determine which firewall it will use to handle the event.
The firewall map returns one if pattern matches the request path.
In our case, we have only one defined firewall, which matches every path ("pattern: ^/"), so our **main** firewall is responsible to authenticate user on every request.

The **main** firewall has many authentication listeners, which could authenticate a user.
Therefore, on kernel request, it iterates through all its listeners, invokes their *handle* methods passing them an event object.
If any listener returns a Response, it breaks the loop and returns the response.
Let's list those listeners.

- Channel Listener
	- Responsible for switching the HTTP protocol
- Context Listener
	- Checks if session exists, takes auth. token from session (if auth. token exists in the session)
	- Gets user from auth. token, then refreshes user by calling UserProvider's refreshUser method; afterwards saves the user in an auth. token and store the token in context.
	- Listens on *onKernelResponse* event - The aim is that it takes the auth. token at the end (if it is still present in context) and stores it back in the session.
- Logout Listener - Active if it is enabled in config. ("logout: " ... )
	- Checks if *logout path* is requested.
	If it is, invokes its Logout Handlers (which could delete some cookies), sets null in context and returns response to the firewall, which interrupts further authentication.
- "Custom Listeners" - will be explained later, those are:
	- *pre_auth* Listeners
	- *form* Listeners
	- *http* Listeners
	- *remember-me* Listeners
- Anonymous Listener - active only if it is enabled config ("anonymous: ~")
	- At this point the listeners have had a chance to generate an auth. token.
	- If no one did that (null set in context) it generates AnonymousToken and sets it in context.
- Access Listener
	- Checks if token is present in context (possible to be set to null if AnonymousListener is inactive)
	- Checks if token is authenticated, if it is not tries to authenticate the token by passing the token to AuthenticationManager.

"Custom Listeners" are where some other Authentication Listeners could be attached.
In this implementation there are two Listeners: Crowd**Login**AuthenticationListener and Crowd**SSO**AuthenticationListener.
The process of "registering" authentication listeners is done through factories which implement the SecurityFactoryInterface (DependencyInjection/Security/Factory).
This interface says that you have to define:

- *position* where the listener will be put in the "Custom Listeners" section, it could take on of the values: *pre_auth*, *form*, *http*, and *remember-me*.
- *key*, which identifies your factory.
In the config (above) we have enabled those factories by listing them in the firewall section (*crowd_form_login* and *crowd-sso*).
- *create* method which should create *entry point*, *authentication listener*, and *authentication provider*.
- *addConfiguration* used for defining parameters that the factory uses. In the example above, *crowd_form_login* factory uses *login_path* and *check_path*.

Now that we have have attached listeners to the firewall, the authentication flow is:
Authentication Listener should take some data from the request (Login Listener takes username and password from login form,
SSO Listener takes SSOCookieToken),
then the listener creates CrowdAuthenticationToken and fills it with the data, the listener invokes authentication of the token.
CrowdAuthenticationProvider receives the auth. token, fetches a CrowdUser from UserProvider with data provided in the auth. token,
stores the CrowdUser in the auth. token. the listener gets the auth. token back, and then it marks the token as authenticated and stores it in the context.

How login is done:
Symfony already provides the AbstractAuthenticationListener which CrowdLoginAuthenticationListener extends.
AbstractAuthenticationListener does some checks (such as checking if the requested path is the path to the login page),
then invokes attemptAuthentication method in CrowdLoginAuthenticationListener.
The method should return the CrowdAuthenticatedToken provided by CrowdAuthenticationProvider,
then AbstractAuthenticationListener invokes SuccessHandler.
So CrowdLoginAuthenticationListener fetches the username and password, sends them in the CrowdAuthenticationToken;
CrowdLoginSuccessHandler sets an SSO Crowd cookie in the response;

Now when the user is logged in, an auth. token is in the session.
On every request, the Login Listener does nothing, except if request path matches login path.
SSO Listener reads the SSO Crowd cookie and since there is an auth. token in the session it sets the authenticated flag on the token and returns it,
it does not need to fetch user from Crowd server on every request.
Anonymous Listener finds the auth. token in the context and does nothing.
Access Listener checks if auth. token is authenticated - it is, and that's it. User stays authenticated.

As mentioned above ContextListener invokes UserProvider's refreshUser method on every request,
It would be unnecessary to fetch user from Crowd on every request,
UserProvider refreshes user only after "some" time, which is defined by a parameter.

If a user requests logout, Logout Listener activates, goes through all its Logout Handlers and invokes their logout methods.
CrowdSSOLogoutHandler deletes SSO Crowd cookie.
