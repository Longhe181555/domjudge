<?php
namespace App\Form\Type;

use App\Entity\ProblemDisplayData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
// use Symfony\Component\Form\Extension\Core\Type\TextareaType; (removed duplicate)
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProblemDisplayDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('displayName', TextType::class, [
                'required' => false,
                'label' => 'Display Name',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description (HTML allowed)',
                'attr' => ['class' => 'js-description-input'],
            ])
            ->add('metaData', TextareaType::class, [
                'required' => false,
                'label' => 'Metadata (JSON)',
                'attr' => ['class' => 'js-metadata-input', 'rows' => 4],
            ])
            ->add('attachmentFile', FileType::class, [
                'required' => false,
                'label' => 'Add Attachment (any file type)',
                'mapped' => false,
                'attr' => ['class' => 'js-attachmentfile-input'],
            ])
            ->add('attachmentLink', TextType::class, [
                'required' => false,
                'label' => 'Attachment Link (URL)',
                'mapped' => false,
                'attr' => ['placeholder' => 'https://example.com/file.zip', 'class' => 'js-attachmentlink-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProblemDisplayData::class,
        ]);
    }
}
