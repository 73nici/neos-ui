<?php
namespace Neos\Neos\Ui\Domain\Model\Feedback\Operations;

use Neos\Neos\Ui\Domain\Model\FeedbackInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Mvc\Controller\ControllerContext;

class DocumentNodeCreated implements FeedbackInterface
{
    /**
     * @var NodeInterface
     */
    protected $document;

    /**
     * Set the document
     *
     * @param NodeInterface $document
     * @return void
     */
    public function setDocumentNode(NodeInterface $document)
    {
        $this->document = $document;
    }

    /**
     * Get the document
     *
     * @return NodeInterface
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Get the type identifier
     *
     * @return string
     */
    public function getType()
    {
        return 'Neos.Neos.Ui:DocumentNodeCreated';
    }

    /**
     * Get the description
     *
     * @return string
     */
    public function getDescription()
    {
        return sprintf('Document Node "%s" created.', $this->getDocument()->getContextPath());
    }

    /**
     * Checks whether this feedback is similar to another
     *
     * @param FeedbackInterface $feedback
     * @return boolean
     */
    public function isSimilarTo(FeedbackInterface $feedback)
    {
        if (!$feedback instanceof DocumentNodeCreated) {
            return false;
        }

        return $this->getDocument()->getContextPath() === $feedback->getDocument()->getContextPath();
    }

    /**
     * Serialize the payload for this feedback
     *
     * @param ControllerContext $controllerContext
     * @return mixed
     */
    public function serializePayload(ControllerContext $controllerContext)
    {
        return [
            'contextPath' => $this->getDocument()->getContextPath(),
            'identifier' => $this->getDocument()->getIdentifier()
        ];
    }
}
