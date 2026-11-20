import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import sms from '@/routes/login/sms';

type Props = {
    phone: string | null;
    codeSent: boolean;
    status?: string;
};

export default function SmsLogin({ phone, codeSent, status }: Props) {
    return (
        <>
            <Head title="Connexion par SMS" />
            {status && (
                <p
                    role="status"
                    className="mb-4 text-center text-sm text-muted-foreground"
                >
                    {status}
                </p>
            )}
            <div className="grid gap-6">
                <Form
                    {...sms.send.form()}
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    Numéro de téléphone
                                </Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    defaultValue={phone ?? ''}
                                    placeholder="+2250102030405"
                                    className="min-h-11 text-base"
                                    required
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <Button
                                className="min-h-11 w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Envoyer un code
                            </Button>
                        </>
                    )}
                </Form>

                {codeSent && (
                    <Form
                        {...sms.verify.form()}
                        className="grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="phone"
                                    value={phone ?? ''}
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="code">
                                        Code à six chiffres
                                    </Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        className="min-h-11 text-base"
                                        required
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <Button
                                    className="min-h-11 w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Se connecter
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href={login()}>Retour à la connexion</TextLink>
                </div>
            </div>
        </>
    );
}

SmsLogin.layout = {
    title: 'Connexion par SMS',
    description: 'Utilise un code temporaire envoyé sur ton numéro vérifié.',
};
