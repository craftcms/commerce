import {
  actionClient,
  showError,
  showNotice,
  type ActionRequestOptions,
} from '@craftcms/cp';

export function sendActionRequest<T = unknown>(
  method: string,
  action: string,
  options: ActionRequestOptions = {}
) {
  return actionClient.request<T>({method, url: action, ...options});
}

export function displayNotice(message: string): void {
  showNotice(message);
}

export function displayError(message?: string | null): void {
  showError(message);
}
