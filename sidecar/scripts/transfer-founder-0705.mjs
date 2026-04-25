import fs from 'fs';
import { AppWallet, KoiosProvider, MeshTxBuilder } from '@meshsdk/core';

const recipient = 'addr_test1qzcs3jcnnemzpkmcw2swetn3t04tca4cw33qa6u06pdfjcmwrsncarlcyqcls7a59hueldz4ljt4xqfdpu9f35y4uuaq4fnpqn';
const unit = 'dc77b92e4acd1887caa43df3f83c093a49990b96128cc8e02069ba4d71642d73696c7665722d30303030373035';
const expectedHolder = 'addr_test1qq7q642xykq3mg04s6mdyvfc7mz96hz4qv6p4ncnwyzhkrdu3908k8zq4lt7upj7nq5f6gk0q4x72kt0246xtmea077s7etvs0';

const envRaw = fs.readFileSync(new URL('../.env', import.meta.url), 'utf8');
const env = {};
for (const ln of envRaw.split(/\r?\n/)) {
  if (!ln || ln.trim().startsWith('#')) continue;
  const i = ln.indexOf('=');
  if (i < 0) continue;
  env[ln.slice(0, i).trim()] = ln.slice(i + 1).trim();
}

const words = (env.POLICY_MNEMONIC_FOUNDERS || '').split(/\s+/).filter(Boolean);
if (words.length !== 24) {
  throw new Error('POLICY_MNEMONIC_FOUNDERS missing/invalid');
}

console.log('stage:init-provider');
const provider = new KoiosProvider('preprod');
const wallet = new AppWallet({
  networkId: 0,
  fetcher: provider,
  submitter: provider,
  key: { type: 'mnemonic', words },
});

const sender = wallet.getPaymentAddress();
console.log('stage:sender', sender);
if (sender !== expectedHolder) {
  throw new Error('Configured policy wallet does not match expected token holder');
}

console.log('stage:fetch-utxos');
const utxos = await provider.fetchAddressUTxOs(sender);
console.log('stage:utxos', utxos.length);

console.log('stage:build');
const txBuilder = new MeshTxBuilder({ fetcher: provider, submitter: provider });
const unsigned = await txBuilder
  .txOut(recipient, [
    { unit: 'lovelace', quantity: '2000000' },
    { unit, quantity: '1' },
  ])
  .changeAddress(sender)
  .selectUtxosFrom(utxos)
  .complete();
console.log('stage:unsigned', unsigned.length);

console.log('stage:sign');
const signed = await wallet.signTx(unsigned);
console.log('stage:signed', signed.length);

console.log('stage:submit');
const txHash = await provider.submitTx(signed);

console.log(JSON.stringify({
  ok: true,
  tx_hash: txHash,
  from: sender,
  to: recipient,
  unit,
}, null, 2));
