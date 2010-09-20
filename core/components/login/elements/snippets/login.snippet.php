<?php
/**
 * Login
 *
 * Copyright 2010 by Jason Coward <jason@modxcms.com> and Shaun McCormick
 * <shaun@modxcms.com>
 *
 * Login is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * Login is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Login; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package login
 */
/**
 * MODx Login Snippet
 *
 * This snippet handles login POSTs, sending the user back to where they came from or to a specific
 * location if specified in the POST.
 *
 * @version 2.0.0
 * @author Jason Coward <jason@modxcms.com>
 * @author Shaun McCormick <shaun@modxcms.com>
 * @copyright Copyright &copy; 2010
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License
 * version 2 or (at your option) any later version.
 * @package login
 *
 * @property textfield actionKey The REQUEST variable containing the action to take.
 * @property textfield loginKey The actionKey for login.
 * @property textfield logoutKey The actionKey for logout.
 * @property list tplType The type of template to expect for the views:
 *  modChunk - name of chunk to use
 *  file - full path to file to use as tpl
 *  embedded - the tpl is embedded in the page content
 *  inline - the tpl is inline content provided directly
 * @property textfield loginTpl The template for the login view (content based on tplType)
 * @property textfield logoutTpl The template for the logout view (content based on tplType)
 * @property textfield errTpl The template for any errors that occur when processing an view
 * @property list errTplType The type of template to expect for the error messages:
 *  modChunk - name of chunk to use
 *  file - full path to file to use as tpl
 *  inline - the tpl is inline content provided directly
 * @property integer logoutResourceId An explicit resource id to redirect users to on logout
 * @property string loginMsg The string to use for the login action. Defaults to
 * the lexicon string "login".
 * @property string logoutMsg The string to use for the logout action. Defaults
 * to the lexicon string "login.logout"
 */
$corePath = $modx->getOption('login.core_path',$scriptProperties,$modx->getOption('core_path',null,MODX_CORE_PATH).'components/login/');
$login = $modx->getService('login','Login',$corePath.'model/login/',$scriptProperties);
if (!is_object($login) || !($login instanceof Login)) return '';

$output = '';
$modx->lexicon->load('login:default');

/* setup default properties */
$preHooks = $modx->getOption('preHooks',$scriptProperties,'');
$loginTpl = $modx->getOption('loginTpl',$scriptProperties,'lgnLoginTpl');
$logoutTpl = $modx->getOption('logoutTpl',$scriptProperties,'lgnLogoutTpl');
$loginMsg = $modx->getOption('loginMsg',$scriptProperties,'');
$logoutMsg = $modx->getOption('logoutMsg',$scriptProperties,'');
$tplType = $modx->getOption('tplType',$scriptProperties,'modChunk');
$actionKey = $modx->getOption('actionKey',$scriptProperties,'service');
$loginKey = $modx->getOption('loginKey',$scriptProperties,'login');
$logoutKey = $modx->getOption('logoutKey',$scriptProperties,'logout');
$errorPrefix = $modx->getOption('errorPrefix',$scriptProperties,'error');
$errTpl = $modx->getOption('errTpl',$scriptProperties,'lgnErrTpl');
$errTplType = $modx->getOption('errTplType',$scriptProperties,'modChunk');
$authenticated = $modx->user->isAuthenticated($modx->context->get('key'));

if (isset($_REQUEST[$actionKey]) && !empty($_REQUEST[$actionKey])) {
    if (isset($_REQUEST['login_context']) && !empty($_REQUEST['login_context'])) {
        $loginContext = $_REQUEST['login_context'];
    }
    
    /* login */
    if (!empty($_POST) && isset($_POST[$actionKey]) && !$authenticated) {
        if ($_POST[$actionKey] == $loginKey) {
            /* set default POST vars if not in form */
            if (!isset($_POST['login_context'])) $_POST['login_context'] = $loginContext;

            /* do pre-login hooks */
            $fields = $_REQUEST;
            $login->loadHooks('loginPrehooks');
            $login->loginPrehooks->loadMultiple($preHooks,$fields,array(
                'mode' => 'login',
            ));

            /* process prehooks */
            if (!empty($login->loginPrehooks->errors)) {
                $modx->toPlaceholders($login->loginPrehooks->errors,$errorPrefix);

                $errorMsg = $login->loginPrehooks->getErrorMessage();
                $errorOutput = $modx->parseChunk($errTpl, array('msg' => $errorMsg));
                $modx->setPlaceholder('errors',$errorOutput);
                
            } else {
                /* send to login processor and handle response */
                $response = $modx->executeProcessor(array(
                    'action' => 'login',
                    'location' => 'security'
                ));
                if (!empty($response) && is_array($response)) {
                    if (!empty($response['success']) && isset($response['object'])) {
                        /* do post hooks */
                        $postHooks = $modx->getOption('postHooks',$scriptProperties,'');
                        $login->loadHooks('posthooks');
                        $fields = $_POST;
                        $fields['response'] =& $response;
                        $fields['loginResourceId'] =& $loginResourceId;
                        $login->posthooks->loadMultiple($postHooks,$fields,array(
                            'mode' => 'login',
                        ));

                        /* process posthooks for login */
                        if (!empty($login->posthooks->errors)) {
                            $modx->toPlaceholders($login->posthooks->errors,$errorPrefix);

                            $errorMsg = $login->posthooks->getErrorMessage();
                            $modx->toPlaceholder('message',$errorMsg,$errorPrefix);
                        } else {
                            $loginResourceId = $modx->getOption('loginResourceId',$scriptProperties,0);
                            /* login posthooks succeeded, now redirect */
                            if (!empty($loginResourceId) && ($url = $modx->makeUrl($loginResourceId, $loginContext, '', 'full'))) {
                                $modx->sendRedirect($url);
                            } elseif (isset($response['object']['url'])) {
                                $modx->sendRedirect($response['object']['url']);
                            } else {
                                $modx->sendRedirect($modx->getOption('site_url'));
                            }
                        }

                    /* logout failed, output error */
                    } else {
                        $errorOutput = '';
                        if (isset($response['errors']) && !empty($response['errors'])) {
                            foreach ($response['errors'] as $error) {
                                $errorOutput .= $modx->parseChunk($errTpl, $error);
                            }
                        } elseif (isset($response['message']) && !empty($response['message'])) {
                            $errorOutput = $modx->parseChunk($errTpl, array('msg' => $response['message']));
                        } else {
                            $errorOutput = $modx->parseChunk($errTpl, array('msg' => $modx->lexicon('login.login_err')));
                        }
                        $modx->setPlaceholder('errors', $errorOutput);
                    }
                }
            }
        } else {
            $modx->log(modX::LOG_LEVEL_ERROR,$modx->lexicon('login.invalid_post',array(
                'action' => $_POST[$actionKey],
            )));
        }

    /* logout */
    } elseif ($_REQUEST[$actionKey] == $logoutKey && $authenticated) {
        /* set default REQUEST vars if not provided */
        if (empty($_REQUEST['login_context'])) $_REQUEST['login_context'] = $loginContext;

        /* do pre-register hooks */
        $fields = $_REQUEST;
        $login->loadHooks('logoutPrehooks');
        $login->logoutPrehooks->loadMultiple($preHooks,$fields,array(
            'mode' => 'logout',
        ));

        /* process prehooks error messages */
        if (!empty($login->logoutPrehooks->errors)) {
            $modx->toPlaceholders($login->logoutPrehooks->errors,$errorPrefix);

            $errorMsg = $login->logoutPrehooks->getErrorMessage();
            $errorOutput = $modx->parseChunk($errTpl, array('msg' => $errorMsg));
            $modx->setPlaceholder('errors',$errorOutput);

        /* prehooks successful, move on */
        } else { 
            /* send to logout processor and handle response */
            $response = $modx->executeProcessor(array(
                'action' => 'logout',
                'location' => 'security'
            ));
            if (!empty($response) && is_array($response)) {
                /* if successful logout */
                if (!empty($response['success']) && isset($response['object'])) {
                    
                    /* do post hooks for logout */
                    $postHooks = $modx->getOption('postHooks',$scriptProperties,'');
                    $login->loadHooks('posthooks');
                    $fields = $_POST;
                    $fields['response'] =& $response;
                    $fields['logoutResourceId'] =& $logoutResourceId;
                    $login->posthooks->loadMultiple($postHooks,$fields,array(
                        'mode' => 'logout',
                    ));

                    /* log posthooks errors */
                    if (!empty($login->posthooks->errors)) {
                        $modx->log(modX::LOG_LEVEL_ERROR,'[Login] Post-Hook errors: '.print_r($login->posthooks->errors,true));

                        $errorMsg = $login->posthooks->getErrorMessage();
                        if (!empty($errorMsg)) {
                            $modx->log(modX::LOG_LEVEL_ERROR,'[Login] Post-Hook error: '.$errorMsg);
                        }
                    }

                    /* redirect */
                    $logoutResourceId = $modx->getOption('logoutResourceId',$scriptProperties,0);
                    if (isset($response['object']['url'])) {
                        $modx->sendRedirect($response['object']['url']);
                    } elseif (!empty($logoutResourceId)) {
                        $modx->sendRedirect($modx->makeUrl($logoutResourceId));
                    } else {
                        $modx->sendRedirect($_SERVER['REQUEST_URI']);
                    }

                /* logout failed, output error */
                } else {
                    $errorOutput = '';
                    if (isset($response['errors']) && !empty($response['errors'])) {
                        foreach ($response['errors'] as $error) {
                            $errorOutput .= $modx->parseChunk($errTpl, $error);
                        }
                    } elseif (isset($response['message']) && !empty($response['message'])) {
                        $errorOutput = $modx->parseChunk($errTpl, array('msg' => $response['message']));
                    } else {
                        $errorOutput = $modx->parseChunk($errTpl, array('msg' => $modx->lexicon('login.logout_err')));
                    }
                    $modx->setPlaceholder('errors', $errorOutput);
                }
            }
        }
    }
}

$redirectToPrior = $modx->getOption('redirectToPrior',$scriptProperties,false);
$tpl = $authenticated ? $logoutTpl : $loginTpl;
$actionMsg = $authenticated
    ? (!empty($logoutMsg) ? $logoutMsg : $modx->lexicon('login.logout'))
    : (!empty($loginMsg) ? $loginMsg : $modx->lexicon('login'));

$modx->setPlaceholder('actionMsg', $actionMsg);
$phs = $authenticated ? $scriptProperties : array_merge($scriptProperties, $_POST);
/* make sure to strip out logout GET parameter to prevent ghost logout */
if (!$redirectToPrior) {
    $phs['request_uri'] = str_replace(array('?service='.$logoutKey,'&service='.$logoutKey,'&amp;service='.$logoutKey),'',$_SERVER['REQUEST_URI']);
} else {
    $phs['request_uri'] = str_replace(array('?service='.$logoutKey,'&service='.$logoutKey,'&amp;service='.$logoutKey),'',$_SERVER['HTTP_REFERER']);
}

/* properly build logout url */
if ($authenticated) {
    $phs['logoutUrl'] = $phs['request_uri'];
    $phs['logoutUrl'] .= strpos($phs['logoutUrl'],'?') ? ($modx->getOption('xhtml_urls',null,false) ? '&amp;' : '&') : '?';
    $phs['logoutUrl'] .= $phs['actionKey'].'='.$phs['logoutKey'];
}

/* if using recaptcha, load recaptcha html */
if (strpos($preHooks,'recaptcha') !== false && !$authenticated) {
    $recaptcha = $modx->getService('recaptcha','reCaptcha',$login->config['modelPath'].'recaptcha/');
    if ($recaptcha instanceof reCaptcha) {
        $modx->lexicon->load('login:recaptcha');
        $recaptchaTheme = $modx->getOption('recaptchaTheme',$scriptProperties,'clean');
        $recaptchaWidth = $modx->getOption('recaptchaWidth',$scriptProperties,500);
        $recaptchaHeight = $modx->getOption('recaptchaHeight',$scriptProperties,300);
        $html = $recaptcha->getHtml($recaptchaTheme,$recaptchaWidth,$recaptchaHeight);
        $modx->setPlaceholder('login.recaptcha_html',$html);
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR,'[Login] '.$this->modx->lexicon('login.recaptcha_err_load'));
    }
}

/* get output of form */
$output = $login->getChunk($tpl,$phs,$tplType);

/* if setting placeholder, set, otherwise, return (commas!) */
$toPlaceholder = $modx->getOption('toPlaceholder',$scriptProperties,'');
if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder,$output);
    return '';
}
return $output;