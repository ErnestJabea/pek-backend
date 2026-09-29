"""Read-only deployment checks. No credentials, OTP, subscription or debit."""
import argparse
import json
import sys
import urllib.error
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--api', required=True, help='HTTPS API base, including /api')
    parser.add_argument('--origin', required=True, help='Exact HTTPS frontend origin, no path')
    args = parser.parse_args()
    if not args.api.startswith('https://') or not args.origin.startswith('https://'):
        parser.error('HTTPS is required for deployment checks')
    opener = urllib.request.build_opener(NoRedirect)
    failures = []

    def check(label, condition):
        print(('OK   ' if condition else 'FAIL ') + label)
        if not condition:
            failures.append(label)

    def request(method, path, origin, preflight=False):
        headers = {'Origin': origin, 'Accept': 'application/json'}
        if preflight:
            headers.update({'Access-Control-Request-Method': 'POST',
                            'Access-Control-Request-Headers': 'content-type,authorization,idempotency-key'})
        req = urllib.request.Request(args.api.rstrip('/') + path, method=method, headers=headers)
        try:
            response = opener.open(req, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read(8192)

    for path in ['/login', '/subscriptions']:
        status, headers, _ = request('OPTIONS', path, args.origin, True)
        check('OPTIONS ' + path + ' returns 204 without redirect', status == 204)
        check(path + ' exact origin, single CORS header', headers.get_all('Access-Control-Allow-Origin') == [args.origin])
        check(path + ' credentials', headers.get('Access-Control-Allow-Credentials') == 'true')
        allowed = {h.strip().lower() for h in headers.get('Access-Control-Allow-Headers', '').split(',')}
        check(path + ' payment/auth headers', {'content-type', 'authorization', 'idempotency-key'} <= allowed)
    _, headers, _ = request('OPTIONS', '/login', 'https://untrusted.invalid', True)
    check('untrusted origin rejected', headers.get('Access-Control-Allow-Origin') not in ['https://untrusted.invalid', '*'])
    status, headers, body = request('GET', '/payment-options', args.origin)
    check('payment-options route exists', status == 200)
    check('actual response CORS', headers.get_all('Access-Control-Allow-Origin') == [args.origin])
    if status == 200:
        data = json.loads(body)
        check('payment-options contract', all(key in data for key in ['orange_money', 'mtn_momo', 's3p_mode']))
        print('Payment availability: ' + json.dumps({k: data.get(k) for k in ['orange_money', 'mtn_momo', 's3p_mode']}))
    status, headers, _ = request('GET', '/user', args.origin)
    check('protected route rejects anonymous request', status == 401)
    check('401 remains readable by frontend', headers.get_all('Access-Control-Allow-Origin') == [args.origin])
    return 1 if failures else 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError) as error:
        print('FAIL transport or response: ' + str(error), file=sys.stderr)
        sys.exit(1)
