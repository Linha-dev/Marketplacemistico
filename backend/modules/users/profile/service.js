import {
  findUserByPhoneExcludingId,
  findUserProfileById,
  updateUserProfile
} from './repository.js';
import { sanitizeProfilePayload, validateProfilePayload } from './schemas.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function getUserProfile(userId) {
  const users = await findUserProfileById(userId);

  if (users.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Usuario nao encontrado');
  }

  return users[0];
}

export async function updateProfile(userId, payload) {
  const sanitized = sanitizeProfilePayload(payload);
  const validation = validateProfilePayload(sanitized);

  if (!validation.ok) {
    throw createBusinessError(validation.code, validation.message);
  }

  const { nome, telefone } = validation.value;

  if (telefone) {
    const existingPhone = await findUserByPhoneExcludingId(telefone, userId);
    if (existingPhone.length > 0) {
      throw createBusinessError('PHONE_TAKEN', 'Telefone ja cadastrado');
    }
  }

  await updateUserProfile({ userId, nome, telefone });
  return getUserProfile(userId);
}
