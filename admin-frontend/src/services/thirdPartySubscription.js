import request from '@/utils/request';

export const fetchThirdPartySubscriptions = (params) => {
  return request('/' + window.settings.secure_path + '/server/third-party/fetch', {
    method: 'GET',
    params,
  });
};

export const saveThirdPartySubscription = (data) => {
  return request('/' + window.settings.secure_path + '/server/third-party/save', {
    method: 'POST',
    data,
  });
};

export const updateThirdPartySubscription = (data) => {
  return request('/' + window.settings.secure_path + '/server/third-party/update', {
    method: 'POST',
    data,
  });
};

export const dropThirdPartySubscription = (id) => {
  return request('/' + window.settings.secure_path + '/server/third-party/drop', {
    method: 'POST',
    data: { id },
  });
};

export const syncThirdPartySubscription = (id) => {
  return request('/' + window.settings.secure_path + '/server/third-party/sync', {
    method: 'POST',
    data: { id },
  });
};

export const syncAllThirdPartySubscriptions = () => {
  return request('/' + window.settings.secure_path + '/server/third-party/sync', {
    method: 'POST',
    data: {},
  });
};

export const statusThirdPartySubscription = (id) => {
  return request('/' + window.settings.secure_path + '/server/third-party/status', {
    method: 'GET',
    params: { id },
  });
};
